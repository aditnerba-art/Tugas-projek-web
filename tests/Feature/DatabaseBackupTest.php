<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BackupFailedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;
use ZipArchive;

class DatabaseBackupTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = storage_path('framework/testing/database-backup-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->temporaryDirectory, 0700, true);
        config([
            'backup.database_directory' => $this->temporaryDirectory.'\\database',
            'backup.keep_days' => 14,
            'backup.photos_directory' => $this->temporaryDirectory.'\\photos',
            'backup.photos_keep_days' => 60,
            'backup.minimum_bytes' => 1024,
            'backup.offsite_disk' => 'backup_offsite',
            'backup.offsite_prefix' => 'test-backups',
            'backup.encryption_key' => base64_encode(random_bytes(32)),
            'filesystems.disks.service_photos' => [
                'driver' => 'local',
                'root' => $this->temporaryDirectory.'\\photo-source',
                'throw' => true,
                'report' => true,
            ],
            'filesystems.disks.backup_offsite' => [
                'driver' => 'local',
                'root' => $this->temporaryDirectory.'\\remote',
                'throw' => true,
                'report' => true,
            ],
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->temporaryDirectory.'\\source.sqlite',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('sqlite');
        File::deleteDirectory($this->temporaryDirectory);
        parent::tearDown();
    }

    public function test_verified_backup_uploads_encrypted_bundle_and_restores_database_and_photos(): void
    {
        DB::table('customers')->insert([
            'nama' => 'Backup Restore Test',
            'no_wa' => '08123456789',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $photoPath = 'service-photos/services/900/restore-test.jpg';
        Storage::disk('service_photos')->put($photoPath, 'private-service-photo-fixture');

        $databaseDirectory = $this->temporaryDirectory.'\\database';
        $photosDirectory = $this->temporaryDirectory.'\\photos';
        File::ensureDirectoryExists($databaseDirectory, 0700, true);
        File::ensureDirectoryExists($photosDirectory, 0700, true);
        File::put($databaseDirectory.'\\database-old.sqlite', 'expired');
        File::put($photosDirectory.'\\service-photos-old.zip', 'still retained');
        touch($databaseDirectory.'\\database-old.sqlite', now()->subDays(40)->getTimestamp());
        touch($photosDirectory.'\\service-photos-old.zip', now()->subDays(40)->getTimestamp());

        $this->artisan('database:backup', ['--verify' => true])
            ->expectsOutput('Verifikasi dump database dan arsip foto berhasil.')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($databaseDirectory.'\\database-old.sqlite');
        $this->assertFileExists($photosDirectory.'\\service-photos-old.zip');
        $databaseBackups = collect(File::files($databaseDirectory))
            ->filter(fn (\SplFileInfo $file) => str_starts_with($file->getFilename(), 'database-') && $file->getExtension() === 'sqlite');
        $photoBackups = collect(File::files($photosDirectory))
            ->filter(fn (\SplFileInfo $file) => str_starts_with($file->getFilename(), 'service-photos-')
                && $file->getFilename() !== 'service-photos-old.zip'
                && $file->getExtension() === 'zip');
        $this->assertCount(1, $databaseBackups);
        $this->assertCount(1, $photoBackups);
        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'backup_berhasil',
            'subjek' => 'database:backup',
        ]);

        $remoteObjects = Storage::disk('backup_offsite')->allFiles('test-backups');
        $this->assertCount(1, $remoteObjects);
        $decryptedArchive = $this->temporaryDirectory.'\\decrypted.zip';
        $this->artisan('database:backup:decrypt', [
            'source' => $remoteObjects[0],
            'destination' => $decryptedArchive,
            '--disk' => 'backup_offsite',
        ])->assertExitCode(0);

        $bundle = new ZipArchive;
        $this->assertTrue($bundle->open($decryptedArchive, ZipArchive::CHECKCONS));
        $databaseEntry = $this->archiveEntryStartingWith($bundle, 'database/');
        $photosEntry = $this->archiveEntryStartingWith($bundle, 'service-photos/');
        $restoredDatabase = $this->temporaryDirectory.'\\restored.sqlite';
        $restoredPhotos = $this->temporaryDirectory.'\\restored-photos.zip';
        $this->copyArchiveEntry($bundle, $databaseEntry, $restoredDatabase);
        $this->copyArchiveEntry($bundle, $photosEntry, $restoredPhotos);
        $bundle->close();

        $restoredConnection = new PDO('sqlite:'.$restoredDatabase);
        $this->assertSame('ok', $restoredConnection->query('PRAGMA integrity_check')->fetchColumn());
        $this->assertSame(
            'Backup Restore Test',
            $restoredConnection->query("SELECT nama FROM customers WHERE no_wa = '08123456789'")->fetchColumn(),
        );

        $photoArchive = new ZipArchive;
        $this->assertTrue($photoArchive->open($restoredPhotos, ZipArchive::CHECKCONS));
        $this->assertSame('private-service-photo-fixture', $photoArchive->getFromName($photoPath));
        $restoredPhoto = $this->temporaryDirectory.'\\restored-private\\'.$photoPath;
        $this->copyArchiveEntry($photoArchive, $photoPath, $restoredPhoto);
        $photoArchive->close();
        $this->assertSame('private-service-photo-fixture', File::get($restoredPhoto));
    }

    public function test_failed_verification_is_logged_and_notifies_owners(): void
    {
        $owner = User::factory()->owner()->create();
        config(['backup.minimum_bytes' => 1000000]);

        $this->artisan('database:backup', ['--verify' => true])
            ->expectsOutputToContain('Backup gagal:')
            ->assertExitCode(1);

        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'backup_gagal',
            'subjek' => 'database:backup',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'type' => BackupFailedNotification::class,
        ]);
    }

    private function archiveEntryStartingWith(ZipArchive $archive, string $prefix): string
    {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            if (is_string($name) && str_starts_with($name, $prefix)) {
                return $name;
            }
        }

        $this->fail("Entry arsip tidak ditemukan: {$prefix}");
    }

    private function copyArchiveEntry(ZipArchive $archive, string $entry, string $destination): void
    {
        $source = $archive->getStream($entry);
        File::ensureDirectoryExists(dirname($destination), 0700, true);
        $target = fopen($destination, 'wb');
        $this->assertIsResource($source);
        $this->assertIsResource($target);
        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);
    }
}
