<?php

return [
    'database_directory' => env('DB_BACKUP_DIRECTORY', storage_path('app/private/backups')),
    'keep_days' => env('DB_BACKUP_KEEP_DAYS', 14),
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
];
