<?php

return [
    'database_directory' => env('DB_BACKUP_DIRECTORY') ?: storage_path('app/private/backups'),
    'keep_days' => env('DB_BACKUP_KEEP_DAYS', 14),
    'photos_directory' => env('DB_PHOTO_BACKUP_DIRECTORY') ?: storage_path('app/private/photo-backups'),
    'photos_keep_days' => env('DB_PHOTO_BACKUP_KEEP_DAYS', 30),
    'minimum_bytes' => env('DB_BACKUP_MINIMUM_BYTES', 1024),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
    'offsite_disk' => env('BACKUP_OFFSITE_DISK'),
    'offsite_prefix' => env('BACKUP_OFFSITE_PREFIX', 'teckfo-backups'),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
];
