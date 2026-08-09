<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Large databases may need more time. Web requests should stay under your
    | PHP / proxy limits; for very large DBs use CLI or scheduled backups.
    |
    */

    'timeout' => (int) env('DB_BACKUP_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Compress SQL backups (.sql.gz)
    |--------------------------------------------------------------------------
    |
    | Strongly recommended for large databases. SQLite file copies are not
    | compressed here because they are already a single binary file.
    |
    */

    'compress' => env('DB_BACKUP_COMPRESS', true),

    /*
    |--------------------------------------------------------------------------
    | Rows per INSERT chunk (PHP fallback exporter)
    |--------------------------------------------------------------------------
    */

    'chunk_rows' => (int) env('DB_BACKUP_CHUNK_ROWS', 500),

    /*
    |--------------------------------------------------------------------------
    | Prefer native CLI tools when available
    |--------------------------------------------------------------------------
    |
    | When true: try mysqldump/mysql (MySQL) or pg_dump/psql (PostgreSQL) first.
    | When unavailable, fall back to pure PHP export/import.
    |
    */

    'prefer_native_tools' => env('DB_BACKUP_PREFER_NATIVE', true),

    /*
    |--------------------------------------------------------------------------
    | Maximum uploaded backup size (megabytes)
    |--------------------------------------------------------------------------
    */

    'max_upload_mb' => (int) env('DB_BACKUP_MAX_UPLOAD_MB', 512),

];
