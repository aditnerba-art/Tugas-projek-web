<?php

namespace App\Console\Commands;

use App\Services\BackupArchiveEncryption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DecryptBackup extends Command
{
    protected $signature = 'database:backup:decrypt {source : Local encrypted file or remote object path} {destination : New local output file} {--disk= : Filesystem disk containing the encrypted source}';

    protected $description = 'Decrypt a verified off-site backup archive';

    public function handle(BackupArchiveEncryption $encryption): int
    {
        $source = (string) $this->argument('source');
        $destination = (string) $this->argument('destination');
        if (! $this->isAbsolutePath($destination)) {
            $destination = base_path($destination);
        }
        if (File::exists($destination)) {
            $this->error('File tujuan sudah ada; dekripsi tidak akan menimpanya.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($destination), 0700, true);
        $stream = null;
        try {
            $disk = trim((string) $this->option('disk'));
            if ($disk !== '') {
                $stream = Storage::disk($disk)->readStream($source);
            } else {
                if (! File::exists($source)) {
                    throw new RuntimeException('File sumber terenkripsi tidak ditemukan.');
                }
                $stream = fopen($source, 'rb');
            }
            if ($stream === false || ! is_resource($stream)) {
                throw new RuntimeException('File sumber terenkripsi tidak dapat dibaca.');
            }

            $encryption->decryptStream($stream, $destination);
            $this->info('Arsip berhasil didekripsi: '.$destination);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (File::exists($destination) && ! File::delete($destination)) {
                $this->error('Dekripsi gagal dan file hasil parsial tidak dapat dihapus: '.$exception->getMessage());

                return self::FAILURE;
            }
            $this->error('Dekripsi gagal: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        $windowsAbsolute = strlen($path) > 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && in_array($path[2], ['\\', '/'], true);

        return str_starts_with($path, DIRECTORY_SEPARATOR) || str_starts_with($path, '/') || $windowsAbsolute;
    }
}
