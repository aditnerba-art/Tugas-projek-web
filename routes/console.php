<?php

use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Process\Process;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:remind-uncollected', function () {
    $queued = app(WhatsAppNotificationService::class)->queuePickupReminders();

    $this->info("Pengingat unit belum diambil dimasukkan ke antrean: {$queued}");
})->purpose('Queue daily WhatsApp reminders for completed units not yet collected');

Artisan::command('database:backup', function () {
    $driver = DB::connection()->getDriverName();
    $directory = config('backup.database_directory') ?: storage_path('app/private/backups');
    $windowsAbsolute = strlen($directory) > 2
        && ctype_alpha($directory[0])
        && $directory[1] === ':'
        && in_array($directory[2], ['\\', '/'], true);
    if (! str_starts_with($directory, DIRECTORY_SEPARATOR) && ! $windowsAbsolute) {
        $directory = base_path($directory);
    }
    File::ensureDirectoryExists($directory, 0700, true);
    $target = null;

    try {
        $extension = match ($driver) {
            'sqlite' => 'sqlite',
            'pgsql' => 'dump',
            'mysql', 'mariadb' => 'sql',
            default => throw new RuntimeException("Driver database {$driver} belum didukung untuk backup otomatis."),
        };
        $target = $directory.DIRECTORY_SEPARATOR.'database-'.now()->format('Ymd-His-u').'.'.$extension;

        if ($driver === 'sqlite') {
            $database = (string) DB::connection()->getDatabaseName();
            if ($database === ':memory:' || $database === '') {
                throw new RuntimeException('Backup SQLite memerlukan database berbasis file, bukan database in-memory.');
            }
            $escapedTarget = str_replace("'", "''", $target);
            DB::connection()->statement("VACUUM INTO '{$escapedTarget}'");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $connection = config('database.connections.'.config('database.default'));
            $arguments = [
                config('backup.mysqldump_binary'),
                '--single-transaction',
                '--quick',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--user='.$connection['username'],
                $connection['database'],
            ];
            $process = new Process($arguments, base_path(), null, null, 3600);
            if ($connection['password'] !== '') {
                $process->setEnv(['MYSQL_PWD' => $connection['password']]);
            }
            $stream = fopen($target, 'wb');
            if ($stream === false) {
                throw new RuntimeException('File backup database tidak dapat dibuat.');
            }
            $stderr = '';
            try {
                $process->run(function (string $type, string $buffer) use ($stream, &$stderr): void {
                    if ($type === Process::OUT) {
                        fwrite($stream, $buffer);
                    } else {
                        $stderr .= $buffer;
                    }
                });
            } finally {
                fclose($stream);
            }
            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($stderr) ?: 'mysqldump gagal dijalankan.');
            }
        } elseif ($driver === 'pgsql') {
            $connection = config('database.connections.'.config('database.default'));
            $arguments = [
                config('backup.pg_dump_binary'),
                '--format=custom',
                '--no-owner',
                '--no-privileges',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--file='.$target,
                $connection['database'],
            ];
            $process = new Process($arguments, base_path(), null, null, 3600);
            if ($connection['password'] !== '') {
                $process->setEnv(['PGPASSWORD' => $connection['password']]);
            }
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'pg_dump gagal dijalankan.');
            }
        }

        if (! File::exists($target) || File::size($target) === 0) {
            throw new RuntimeException('Backup tidak terbentuk atau berukuran kosong.');
        }
    } catch (Throwable $exception) {
        if ($target) {
            File::delete($target);
        }
        Log::error('Backup database gagal.', [
            'driver' => $driver,
            'error' => $exception->getMessage(),
        ]);
        $this->error('Backup database gagal: '.$exception->getMessage());

        return Command::FAILURE;
    }

    $keepDays = max(1, (int) config('backup.keep_days', 14));
    $cutoff = now()->subDays($keepDays)->getTimestamp();
    foreach (File::files($directory) as $backupFile) {
        if (str_starts_with($backupFile->getFilename(), 'database-')
            && in_array($backupFile->getExtension(), ['sqlite', 'sql', 'dump'], true)
            && $backupFile->getMTime() < $cutoff) {
            File::delete($backupFile->getPathname());
        }
    }

    $this->info('Backup database berhasil dibuat: '.$target);

    return Command::SUCCESS;
})->purpose('Create a private database backup and remove expired backup files');

Schedule::command('notifications:remind-uncollected')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('database:backup')->dailyAt('02:00')->withoutOverlapping();
