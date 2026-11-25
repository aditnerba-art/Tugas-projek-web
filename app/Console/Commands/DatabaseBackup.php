<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\BackupFailedNotification;
use App\Services\BackupArchiveEncryption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class DatabaseBackup extends Command
{
    protected $signature = 'database:backup {--verify : Check the database dump and photo archive before keeping or uploading them}';

    protected $description = 'Create private database and service-photo backups, with optional encrypted off-site copy';

    public function handle(BackupArchiveEncryption $encryption): int
    {
        $driver = 'unknown';
        $databaseBackup = null;
        $photoBackup = null;
        $offsiteObject = null;

        try {
            $driver = DB::connection()->getDriverName();
            $offsiteDisk = trim((string) config('backup.offsite_disk'));
            if ($offsiteDisk !== '') {
                $encryption->assertConfigured();
                $diskConfig = config('filesystems.disks.'.$offsiteDisk);
                if (! is_array($diskConfig)) {
                    throw new RuntimeException("Disk filesystem off-site tidak ditemukan: {$offsiteDisk}");
                }
                if (($diskConfig['driver'] ?? null) === 'sftp' && empty($diskConfig['hostFingerprint'])) {
                    throw new RuntimeException('SFTP_HOST_FINGERPRINT wajib diisi untuk memverifikasi server SFTP.');
                }
            }

            $databaseBackup = $this->createDatabaseBackup($driver);
            $photoBackup = $this->createPhotoBackup();

            if ($this->option('verify')) {
                $this->verifyDatabaseBackup($databaseBackup, $driver);
                $this->verifyPhotoBackup($photoBackup['path']);
                $this->info('Verifikasi dump database dan arsip foto berhasil.');
            }

            if ($offsiteDisk !== '') {
                $offsiteObject = $this->uploadOffsite($databaseBackup, $photoBackup['path'], $driver, $offsiteDisk, $encryption);
            }

            $this->pruneBackups(
                $this->absoluteDirectory((string) config('backup.database_directory')),
                'database-',
                ['sqlite', 'sql', 'dump'],
                (int) config('backup.keep_days', 14),
            );
            $this->pruneBackups(
                $this->absoluteDirectory((string) config('backup.photos_directory')),
                'service-photos-',
                ['zip'],
                (int) config('backup.photos_keep_days', 30),
            );

            $this->recordBackupActivity(true, $driver, $databaseBackup, $photoBackup, $offsiteDisk, $offsiteObject);
            $this->info('Backup database berhasil dibuat: '.$databaseBackup);
            $this->info('Backup foto servis berhasil dibuat: '.$photoBackup['path']);
            if ($offsiteObject !== null) {
                $this->info("Salinan terenkripsi diunggah ke disk {$offsiteDisk}: {$offsiteObject}");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $message = $this->safeError($exception->getMessage());
            Log::error('Backup database dan foto gagal.', [
                'driver' => $driver,
                'error' => $message,
            ]);
            $this->recordBackupActivity(false, $driver, $databaseBackup, $photoBackup, null, null, $message);
            $this->notifyOwners($message);
            $this->error('Backup gagal: '.$message);

            return self::FAILURE;
        }
    }

    private function createDatabaseBackup(string $driver): string
    {
        $extension = match ($driver) {
            'sqlite' => 'sqlite',
            'pgsql' => 'dump',
            'mysql', 'mariadb' => 'sql',
            default => throw new RuntimeException("Driver database {$driver} belum didukung untuk backup otomatis."),
        };
        $directory = $this->absoluteDirectory((string) config('backup.database_directory'));
        File::ensureDirectoryExists($directory, 0700, true);
        $target = $directory.DIRECTORY_SEPARATOR.'database-'.now()->format('Ymd-His-u').'-'.Str::random(6).'.'.$extension;

        try {
            if ($driver === 'sqlite') {
                $escapedTarget = str_replace("'", "''", $target);
                DB::connection()->statement("VACUUM INTO '{$escapedTarget}'");
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $this->createMysqlDump($target);
            } else {
                $this->createPostgresDump($target);
            }

            if (! File::exists($target) || File::size($target) === 0) {
                throw new RuntimeException('Dump database tidak terbentuk atau berukuran kosong.');
            }
        } catch (Throwable $exception) {
            if (File::exists($target) && ! File::delete($target)) {
                throw new RuntimeException('Dump gagal dan file parsial tidak dapat dihapus.', 0, $exception);
            }

            throw $exception;
        }

        return $target;
    }

    private function createMysqlDump(string $target): void
    {
        $connection = DB::connection()->getConfig();
        $optionFile = tempnam(sys_get_temp_dir(), 'mysql-backup-');
        if ($optionFile === false) {
            throw new RuntimeException('File opsi MySQL sementara tidak dapat dibuat.');
        }

        try {
            $options = "[client]\n"
                .'host='.$this->mysqlOption((string) ($connection['host'] ?? '127.0.0.1'))."\n"
                .'port='.$this->mysqlOption((string) ($connection['port'] ?? 3306))."\n"
                .'user='.$this->mysqlOption((string) ($connection['username'] ?? ''))."\n"
                .'password='.$this->mysqlOption((string) ($connection['password'] ?? ''))."\n";
            if (file_put_contents($optionFile, $options, LOCK_EX) !== strlen($options)) {
                throw new RuntimeException('File opsi MySQL sementara gagal ditulis.');
            }
            if (! chmod($optionFile, 0600)) {
                throw new RuntimeException('Izin file opsi MySQL sementara tidak dapat dibatasi.');
            }

            $process = new Process([
                config('backup.mysqldump_binary'),
                '--defaults-extra-file='.$optionFile,
                '--single-transaction',
                '--quick',
                '--result-file='.$target,
                (string) ($connection['database'] ?? ''),
            ], base_path(), null, null, 3600);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump gagal dijalankan.');
            }
        } finally {
            if (is_file($optionFile) && ! unlink($optionFile)) {
                throw new RuntimeException('File opsi MySQL sementara tidak dapat dihapus setelah backup.');
            }
        }
    }

    private function mysqlOption(string $value): string
    {
        if (preg_match('/[\r\n\x00]/', $value) === 1) {
            throw new RuntimeException('Nilai konfigurasi MySQL tidak valid untuk file opsi.');
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function createPostgresDump(string $target): void
    {
        $connection = DB::connection()->getConfig();
        $process = new Process([
            config('backup.pg_dump_binary'),
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 5432),
            '--username='.(string) ($connection['username'] ?? ''),
            '--file='.$target,
            (string) ($connection['database'] ?? ''),
        ], base_path(), null, null, 3600);
        if (($connection['password'] ?? '') !== '') {
            $process->setEnv(['PGPASSWORD' => (string) $connection['password']]);
        }
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'pg_dump gagal dijalankan.');
        }
    }

    private function createPhotoBackup(): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk membuat arsip foto servis.');
        }

        $directory = $this->absoluteDirectory((string) config('backup.photos_directory'));
        File::ensureDirectoryExists($directory, 0700, true);
        $target = $directory.DIRECTORY_SEPARATOR.'service-photos-'.now()->format('Ymd-His-u').'-'.Str::random(6).'.zip';
        $disk = Storage::disk('service_photos');
        if (config('filesystems.disks.service_photos.driver') !== 'local') {
            throw new RuntimeException('Backup foto servis saat ini memerlukan disk service_photos lokal.');
        }

        $files = $disk->allFiles('service-photos');
        $diskRoot = $files === [] ? null : realpath($disk->path(''));
        if ($files !== [] && $diskRoot === false) {
            throw new RuntimeException('Root disk foto servis tidak dapat diverifikasi.');
        }
        $archive = new ZipArchive;
        if ($archive->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            if (File::exists($target) && ! File::delete($target)) {
                throw new RuntimeException('Arsip foto gagal dibuat dan file parsial tidak dapat dihapus.');
            }
            throw new RuntimeException('Arsip foto servis tidak dapat dibuat.');
        }

        try {
            foreach ($files as $path) {
                $entry = str_replace('\\', '/', $path);
                if (! str_starts_with($entry, 'service-photos/') || str_contains('/'.$entry.'/', '/../')) {
                    throw new RuntimeException('Path unggahan servis tidak valid untuk backup.');
                }
                $sourcePath = $disk->path($path);
                $sourceRealPath = realpath($sourcePath);
                $rootPrefix = rtrim((string) $diskRoot, '\\/').DIRECTORY_SEPARATOR;
                $pathPrefixMatches = DIRECTORY_SEPARATOR === '\\'
                    ? str_starts_with(strtolower((string) $sourceRealPath), strtolower($rootPrefix))
                    : str_starts_with((string) $sourceRealPath, $rootPrefix);
                $currentPath = (string) $diskRoot;
                foreach (explode('/', $entry) as $segment) {
                    $currentPath .= DIRECTORY_SEPARATOR.$segment;
                    if (is_link($currentPath)) {
                        throw new RuntimeException('Path unggahan servis tidak boleh melewati symbolic link.');
                    }
                }
                if (! is_file($sourcePath)
                    || ! is_string($sourceRealPath)
                    || ! $pathPrefixMatches
                    || ! $archive->addFile($sourcePath, $entry)) {
                    throw new RuntimeException("File foto servis tidak dapat ditambahkan ke arsip: {$entry}");
                }
            }

            $manifest = json_encode([
                'created_at' => now()->toIso8601String(),
                'file_count' => count($files),
                'files' => array_map(fn (string $path) => str_replace('\\', '/', $path), $files),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (! $archive->addFromString('manifest.json', $manifest)) {
                throw new RuntimeException('Manifest arsip foto servis tidak dapat ditulis.');
            }
            if (! $archive->close()) {
                throw new RuntimeException('Arsip foto servis gagal diselesaikan.');
            }
        } catch (Throwable $exception) {
            $archive->close();
            if (File::exists($target) && ! File::delete($target)) {
                throw new RuntimeException('Pembuatan arsip foto gagal dan file parsial tidak dapat dihapus.', 0, $exception);
            }

            throw $exception;
        }

        return ['path' => $target, 'file_count' => count($files)];
    }

    private function verifyDatabaseBackup(string $path, string $driver): void
    {
        $size = File::size($path);
        $minimumBytes = max(1024, (int) config('backup.minimum_bytes', 1024));
        if ($size < $minimumBytes) {
            throw new RuntimeException("Ukuran dump database tidak masuk akal: {$size} byte (minimum {$minimumBytes} byte).");
        }

        if ($driver === 'sqlite') {
            $database = new \PDO('sqlite:'.$path);
            $integrity = $database->query('PRAGMA integrity_check')?->fetchColumn();
            if ($integrity !== 'ok') {
                throw new RuntimeException('Verifikasi integritas dump SQLite gagal.');
            }

            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Dump database tidak dapat dibuka untuk diverifikasi.');
        }
        try {
            $header = fread($handle, 8192);
        } finally {
            fclose($handle);
        }

        if (! is_string($header)
            || ($driver === 'pgsql' && ! str_starts_with($header, 'PGDMP'))
            || (in_array($driver, ['mysql', 'mariadb'], true)
                && (str_contains($header, "\0") || preg_match('/CREATE TABLE|INSERT INTO/i', $header) !== 1))) {
            throw new RuntimeException('Format atau isi dump database tidak lolos verifikasi.');
        }
    }

    private function verifyPhotoBackup(string $path): void
    {
        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Arsip foto servis kosong atau rusak.');
        }

        $hasManifest = $archive->locateName('manifest.json') !== false;
        $archive->close();
        if (! $hasManifest) {
            throw new RuntimeException('Manifest tidak ditemukan pada arsip foto servis.');
        }
    }

    private function uploadOffsite(
        string $databaseBackup,
        string $photoBackup,
        string $driver,
        string $diskName,
        BackupArchiveEncryption $encryption,
    ): string {
        $directory = $this->absoluteDirectory((string) config('backup.database_directory'));
        $bundle = tempnam($directory, '.offsite-backup-');
        if ($bundle === false) {
            throw new RuntimeException('Arsip sementara untuk salinan off-site tidak dapat dibuat.');
        }
        $encrypted = $bundle.'.enc';
        $archive = new ZipArchive;
        $archiveOpen = false;

        try {
            if ($archive->open($bundle, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arsip gabungan off-site tidak dapat dibuat.');
            }
            $archiveOpen = true;
            if (! $archive->addFile($databaseBackup, 'database/'.basename($databaseBackup))
                || ! $archive->addFile($photoBackup, 'service-photos/'.basename($photoBackup))
                || ! $archive->addFromString('manifest.json', json_encode([
                    'created_at' => now()->toIso8601String(),
                    'database_driver' => $driver,
                    'database_file' => basename($databaseBackup),
                    'photos_file' => basename($photoBackup),
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Arsip gabungan off-site tidak dapat disusun.');
            }
            if (! $archive->close()) {
                throw new RuntimeException('Arsip gabungan off-site gagal diselesaikan.');
            }
            $archiveOpen = false;

            $encryption->encryptFile($bundle, $encrypted);
            $prefix = trim(str_replace('\\', '/', (string) config('backup.offsite_prefix')), '/');
            if (in_array('..', explode('/', $prefix), true)) {
                throw new RuntimeException('Prefix disk off-site tidak boleh memuat segmen ..');
            }
            $objectPath = ($prefix === '' ? '' : $prefix.'/').'full-backup-'.now()->format('Ymd-His-u').'-'.Str::random(6).'.zip.enc';
            $stream = fopen($encrypted, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Arsip terenkripsi tidak dapat dibaca untuk diunggah.');
            }
            try {
                if (! Storage::disk($diskName)->put($objectPath, $stream)) {
                    throw new RuntimeException('Unggahan backup terenkripsi ke disk off-site gagal.');
                }
            } finally {
                fclose($stream);
            }

            return $objectPath;
        } finally {
            if ($archiveOpen) {
                $archive->close();
            }
            $cleanupFailures = [];
            foreach ([$bundle, $encrypted] as $temporaryFile) {
                if (is_file($temporaryFile) && ! unlink($temporaryFile)) {
                    $cleanupFailures[] = basename($temporaryFile);
                }
            }
            if ($cleanupFailures !== []) {
                throw new RuntimeException('File sementara off-site tidak dapat dihapus: '.implode(', ', $cleanupFailures));
            }
        }
    }

    private function pruneBackups(string $directory, string $prefix, array $extensions, int $keepDays): void
    {
        $cutoff = now()->subDays(max(1, $keepDays))->getTimestamp();
        foreach (File::files($directory) as $backupFile) {
            if (str_starts_with($backupFile->getFilename(), $prefix)
                && in_array(strtolower($backupFile->getExtension()), $extensions, true)
                && $backupFile->getMTime() < $cutoff
                && ! File::delete($backupFile->getPathname())) {
                throw new RuntimeException("Backup lama tidak dapat dihapus: {$backupFile->getFilename()}");
            }
        }
    }

    private function recordBackupActivity(
        bool $successful,
        string $driver,
        ?string $databaseBackup,
        ?array $photoBackup,
        ?string $offsiteDisk,
        ?string $offsiteObject,
        ?string $error = null,
    ): void {
        $data = [
            'hasil' => $successful ? 'berhasil' : 'gagal',
            'driver' => $driver,
            'database_file' => $databaseBackup ? basename($databaseBackup) : null,
            'database_bytes' => $databaseBackup && File::exists($databaseBackup) ? File::size($databaseBackup) : null,
            'photos_file' => $photoBackup ? basename($photoBackup['path']) : null,
            'photos_count' => $photoBackup['file_count'] ?? null,
            'offsite_disk' => $offsiteDisk,
            'offsite_object' => $offsiteObject,
            'error' => $error,
        ];

        try {
            ActivityLog::create([
                'aksi' => $successful ? 'backup_berhasil' : 'backup_gagal',
                'subjek' => 'database:backup',
                'data_baru' => $data,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::critical('Hasil backup tidak dapat dicatat ke activity_logs.', [
                'hasil' => $successful ? 'berhasil' : 'gagal',
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyOwners(string $error): void
    {
        try {
            $owners = User::query()->where('role', 'owner')->get();
            if ($owners->isEmpty()) {
                Log::warning('Backup gagal tetapi tidak ada akun owner untuk menerima notifikasi.');

                return;
            }

            $notification = new BackupFailedNotification('Backup database/foto gagal: '.$error);
            foreach ($owners as $owner) {
                $owner->notify($notification);
            }
        } catch (Throwable $exception) {
            Log::error('Notifikasi kegagalan backup tidak dapat dikirim ke owner.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function safeError(string $message): string
    {
        $message = preg_replace('/((?:password|passwd|secret)\s*[=:]\s*)[^\s]+/i', '$1[redacted]', $message) ?? $message;

        return Str::limit($message, 1000, '...');
    }

    private function absoluteDirectory(string $directory): string
    {
        if ($directory === '') {
            throw new RuntimeException('Direktori backup tidak boleh kosong.');
        }
        $windowsAbsolute = strlen($directory) > 2
            && ctype_alpha($directory[0])
            && $directory[1] === ':'
            && in_array($directory[2], ['\\', '/'], true);
        if (! str_starts_with($directory, DIRECTORY_SEPARATOR) && ! $windowsAbsolute) {
            return base_path($directory);
        }

        return $directory;
    }
}
