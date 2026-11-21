<?php

namespace App\Console\Commands;

use App\Models\ServicePhoto;
use App\Services\ServicePhotoImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MigrateServicePhotosToPrivate extends Command
{
    protected $signature = 'service-photos:migrate-private {--dry-run : Inspect without changing files or database}';

    protected $description = 'Move legacy service photos from public storage to private storage';

    public function handle(ServicePhotoImageProcessor $imageProcessor): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('service_photos');
        $dryRun = (bool) $this->option('dry-run');
        $migrated = 0;
        $failed = 0;

        foreach (ServicePhoto::query()->orderBy('id')->cursor() as $photo) {
            $legacyPublicPaths = [];
            $newPrivatePaths = [];
            $databaseUpdated = false;
            $needsMigration = false;

            try {
                $files = [];
                foreach (['path' => 90, 'thumbnail_path' => 82] as $column => $quality) {
                    $currentPath = $photo->{$column};
                    if (! is_string($currentPath) || str_contains($currentPath, '..')) {
                        throw new RuntimeException('Path foto database tidak valid.');
                    }
                    $isPrivate = str_starts_with($currentPath, 'service-photos/');
                    $privatePath = $isPrivate ? $currentPath : 'service-photos/'.$currentPath;
                    $legacyPath = $isPrivate ? substr($currentPath, strlen('service-photos/')) : $currentPath;
                    $sourceDisk = $isPrivate ? $private : $public;
                    $sourcePath = $isPrivate ? $currentPath : $legacyPath;

                    if (! $sourceDisk->exists($sourcePath)) {
                        throw new RuntimeException("File sumber tidak ditemukan: {$sourcePath}");
                    }
                    if (! str_starts_with($legacyPath, 'services/')) {
                        throw new RuntimeException("Path foto legacy tidak valid: {$currentPath}");
                    }

                    $files[$column] = [
                        'current' => $currentPath,
                        'private' => $privatePath,
                        'legacy' => $legacyPath,
                        'is_private' => $isPrivate,
                        'quality' => $quality,
                    ];

                    if (! $isPrivate) {
                        $legacyPublicPaths[] = $legacyPath;
                        $needsMigration = true;
                    } elseif ($public->exists($legacyPath)) {
                        $legacyPublicPaths[] = $legacyPath;
                        $needsMigration = true;
                    }
                }

                if (! $needsMigration) {
                    $this->line("Foto servis #{$photo->id} sudah privat.");

                    continue;
                }

                if ($dryRun) {
                    $this->line("Akan memindahkan foto servis #{$photo->id} ke disk privat.");
                    $migrated++;

                    continue;
                }

                foreach ($files as $column => $file) {
                    if ($file['is_private']) {
                        continue;
                    }

                    $contents = $public->get($file['legacy']);
                    $encoded = $imageProcessor->reencode($contents, $file['quality']);
                    if (! $private->put($file['private'], $encoded)) {
                        throw new RuntimeException("File privat gagal ditulis: {$file['private']}");
                    }
                    $newPrivatePaths[] = $file['private'];
                    $files[$column]['size'] = strlen($encoded);
                }

                DB::transaction(function () use ($photo, $files): void {
                    $photo->forceFill([
                        'path' => $files['path']['private'],
                        'thumbnail_path' => $files['thumbnail_path']['private'],
                        'mime_type' => 'image/jpeg',
                        'size' => $files['path']['size'] ?? $photo->size,
                    ])->save();
                });
                $databaseUpdated = true;

                if ($legacyPublicPaths !== [] && ! $public->delete($legacyPublicPaths)) {
                    throw new RuntimeException('Path database sudah privat, tetapi file lama pada disk public gagal dihapus.');
                }

                $migrated++;
            } catch (Throwable $exception) {
                if (! $databaseUpdated) {
                    foreach ($newPrivatePaths as $newPrivatePath) {
                        $private->delete($newPrivatePath);
                    }
                }
                $failed++;
                $this->error("Foto servis #{$photo->id}: {$exception->getMessage()}");
            }
        }

        $this->info(($dryRun ? 'Dry-run selesai' : 'Migrasi selesai').": {$migrated} foto, {$failed} gagal.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
