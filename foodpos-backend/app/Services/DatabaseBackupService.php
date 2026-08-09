<?php

namespace App\Services;

use App\Support\DatabaseBackup\PhpMysqlDumper;
use App\Support\DatabaseBackup\PhpMysqlRestorer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseBackupService
{
    public const BACKUP_DIR = 'database-backups';

    /**
     * @return Collection<int, array{
     *     filename: string,
     *     driver: string,
     *     size: int,
     *     size_label: string,
     *     created_at: \Carbon\Carbon,
     *     method: string
     * }>
     */
    public function listBackups(): Collection
    {
        $this->ensureBackupDirectory();

        return collect(Storage::disk('local')->files(self::BACKUP_DIR))
            ->filter(fn (string $path) => $this->isValidBackupFilename(basename($path)))
            ->map(function (string $path) {
                $filename = basename($path);
                $fullPath = Storage::disk('local')->path($path);

                return [
                    'filename' => $filename,
                    'driver' => $this->driverFromFilename($filename),
                    'size' => (int) filesize($fullPath),
                    'size_label' => $this->formatBytes((int) filesize($fullPath)),
                    'created_at' => \Carbon\Carbon::createFromTimestamp(filemtime($fullPath)),
                    'method' => $this->methodFromFilename($filename),
                ];
            })
            ->sortByDesc(fn (array $backup) => $backup['created_at']->timestamp)
            ->values();
    }

    /**
     * @return array{filename: string, driver: string, size: int, size_label: string, method: string}
     */
    public function createBackup(?string $label = null): array
    {
        $this->ensureBackupDirectory();
        $this->prepareLongRunningRequest();

        $driver = (string) config('database.default');
        $timestamp = now()->format('Y-m-d_His');
        $prefix = $label ? $this->sanitizeLabel($label).'-'.$timestamp : $timestamp;
        $filename = match ($driver) {
            'mysql' => 'backup-mysql-'.$prefix.'.'.$this->sqlExtension(),
            'pgsql' => 'backup-pgsql-'.$prefix.'.'.$this->sqlExtension(),
            'sqlite' => "backup-sqlite-{$prefix}.sqlite",
            default => throw new RuntimeException("Database driver [{$driver}] is not supported for backups."),
        };

        if (! $this->isValidBackupFilename($filename)) {
            throw new RuntimeException('Generated backup filename is invalid.');
        }

        $relativePath = self::BACKUP_DIR.'/'.$filename;
        $absolutePath = Storage::disk('local')->path($relativePath);

        $method = match ($driver) {
            'mysql' => $this->dumpMysql($absolutePath),
            'pgsql' => $this->dumpPgsql($absolutePath),
            'sqlite' => $this->dumpSqlite($absolutePath) ?? 'file-copy',
            default => throw new RuntimeException("Database driver [{$driver}] is not supported for backups."),
        };

        if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
            @unlink($absolutePath);
            throw new RuntimeException('Backup file was not created or is empty.');
        }

        $size = (int) filesize($absolutePath);

        return [
            'filename' => $filename,
            'driver' => $driver,
            'size' => $size,
            'size_label' => $this->formatBytes($size),
            'method' => $method,
        ];
    }

    public function resolveBackupPath(string $filename): string
    {
        $filename = basename($filename);

        if (! $this->isValidBackupFilename($filename)) {
            throw new RuntimeException('Invalid backup file.');
        }

        $relativePath = self::BACKUP_DIR.'/'.$filename;
        $absolutePath = Storage::disk('local')->path($relativePath);

        if (! is_file($absolutePath)) {
            throw new RuntimeException('Backup file not found.');
        }

        return $absolutePath;
    }

    public function restore(string $filename): void
    {
        $this->prepareLongRunningRequest();

        $absolutePath = $this->resolveBackupPath($filename);
        $driver = (string) config('database.default');
        $backupDriver = $this->driverFromFilename($filename);

        if ($backupDriver !== $driver) {
            throw new RuntimeException("Backup driver [{$backupDriver}] does not match current database driver [{$driver}].");
        }

        $this->createBackup('pre-restore');

        match ($driver) {
            'mysql' => $this->restoreMysql($absolutePath),
            'pgsql' => $this->restorePgsql($absolutePath),
            'sqlite' => $this->restoreSqlite($absolutePath),
            default => throw new RuntimeException("Database driver [{$driver}] is not supported for restore."),
        };
    }

    public function delete(string $filename): void
    {
        $filename = basename($filename);

        if (! $this->isValidBackupFilename($filename)) {
            throw new RuntimeException('Invalid backup file.');
        }

        Storage::disk('local')->delete(self::BACKUP_DIR.'/'.$filename);
    }

    public function isValidBackupFilename(string $filename): bool
    {
        return (bool) preg_match('/^backup-(mysql|pgsql|sqlite)-(?:[a-z0-9-]+-)?\d{4}-\d{2}-\d{2}_\d{6}\.(?:sql(?:\.gz)?|sqlite)$/', $filename);
    }

    public function usesCompression(): bool
    {
        return (bool) config('database_backup.compress', true);
    }

    public function preferredNativeToolsEnabled(): bool
    {
        return (bool) config('database_backup.prefer_native_tools', true);
    }

    protected function driverFromFilename(string $filename): string
    {
        if (preg_match('/^backup-(mysql|pgsql|sqlite)-/', $filename, $matches)) {
            return $matches[1];
        }

        throw new RuntimeException('Unable to determine backup driver.');
    }

    protected function methodFromFilename(string $filename): string
    {
        if (str_contains($filename, '-uploaded-')) {
            return 'Uploaded';
        }

        if (str_contains($filename, '-php-')) {
            return 'PHP exporter';
        }

        if (str_ends_with($filename, '.sql.gz')) {
            return 'Compressed SQL';
        }

        if (str_ends_with($filename, '.sql')) {
            return 'SQL dump';
        }

        return 'SQLite file';
    }

    /**
     * Store an uploaded .sql.gz dump in the backups directory.
     *
     * @return array{filename: string, driver: string, size: int, size_label: string, method: string}
     */
    public function importUploadedSqlGz(UploadedFile $file): array
    {
        $this->ensureBackupDirectory();

        $driver = (string) config('database.default');
        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new RuntimeException('SQL uploads are only supported when the app uses MySQL or PostgreSQL.');
        }

        $originalName = strtolower($file->getClientOriginalName());
        if (! str_ends_with($originalName, '.sql.gz')) {
            throw new RuntimeException('Uploaded file must be a .sql.gz backup.');
        }

        $maxBytes = (int) config('database_backup.max_upload_mb', 512) * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException('Uploaded backup exceeds the maximum allowed size of '.config('database_backup.max_upload_mb', 512).' MB.');
        }

        if (! $this->isGzipFile($file->getRealPath() ?: '')) {
            throw new RuntimeException('Uploaded file is not a valid gzip archive.');
        }

        if (! $this->looksLikeSqlDump($file->getRealPath() ?: '')) {
            throw new RuntimeException('Uploaded file does not look like a SQL database dump.');
        }

        $filename = $this->generateUploadedFilename($driver);
        $relativePath = self::BACKUP_DIR.'/'.$filename;
        $absolutePath = Storage::disk('local')->path($relativePath);

        $file->move(dirname($absolutePath), basename($absolutePath));

        if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
            @unlink($absolutePath);
            throw new RuntimeException('Uploaded backup could not be saved.');
        }

        $size = (int) filesize($absolutePath);

        return [
            'filename' => $filename,
            'driver' => $driver,
            'size' => $size,
            'size_label' => $this->formatBytes($size),
            'method' => 'Uploaded',
        ];
    }

    protected function sanitizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = preg_replace('/[^a-z0-9-]+/', '-', $label) ?? 'backup';
        $label = trim($label, '-');

        return $label !== '' ? $label : 'backup';
    }

    protected function sqlExtension(): string
    {
        return $this->usesCompression() ? 'sql.gz' : 'sql';
    }

    protected function ensureBackupDirectory(): void
    {
        Storage::disk('local')->makeDirectory(self::BACKUP_DIR);
    }

    protected function prepareLongRunningRequest(): void
    {
        @set_time_limit((int) config('database_backup.timeout', 3600));
    }

    protected function dumpMysql(string $absolutePath): string
    {
        if ($this->preferredNativeToolsEnabled() && $this->binaryAvailable('mysqldump')) {
            $this->dumpMysqlNative($absolutePath);

            return $this->usesCompression() ? 'mysqldump + gzip' : 'mysqldump';
        }

        app(PhpMysqlDumper::class, [
            'chunkRows' => (int) config('database_backup.chunk_rows', 500),
        ])->dump($absolutePath, $this->usesCompression());

        return $this->usesCompression() ? 'PHP exporter + gzip' : 'PHP exporter';
    }

    protected function restoreMysql(string $absolutePath): void
    {
        $compressed = str_ends_with(strtolower($absolutePath), '.gz');

        if (! $compressed && $this->preferredNativeToolsEnabled() && $this->binaryAvailable('mysql')) {
            $this->restoreMysqlNative($absolutePath);

            return;
        }

        app(PhpMysqlRestorer::class)->restore($absolutePath);
    }

    protected function dumpMysqlNative(string $absolutePath): void
    {
        $config = config('database.connections.mysql');

        if (empty($config['database'])) {
            throw new RuntimeException('MySQL database name is not configured.');
        }

        $defaultsFile = $this->writeMysqlDefaultsFile([
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => (string) ($config['port'] ?? '3306'),
            'user' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
        ]);

        try {
            $dumpCommand = sprintf(
                'mysqldump --defaults-extra-file=%s --single-transaction --routines --triggers --no-tablespaces %s',
                escapeshellarg($defaultsFile),
                escapeshellarg((string) $config['database'])
            );

            if ($this->usesCompression()) {
                if (! $this->binaryAvailable('gzip')) {
                    throw new RuntimeException('gzip is required for compressed backups but is not available.');
                }

                $command = sprintf('%s | gzip -c > %s', $dumpCommand, escapeshellarg($absolutePath));
            } else {
                $command = sprintf('%s > %s', $dumpCommand, escapeshellarg($absolutePath));
            }

            $result = Process::timeout($this->processTimeout())->run(['bash', '-lc', $command]);

            if (! $result->successful()) {
                throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'mysqldump failed.');
            }
        } finally {
            @unlink($defaultsFile);
        }
    }

    protected function restoreMysqlNative(string $absolutePath): void
    {
        $config = config('database.connections.mysql');

        if (empty($config['database'])) {
            throw new RuntimeException('MySQL database name is not configured.');
        }

        DB::disconnect('mysql');

        $defaultsFile = $this->writeMysqlDefaultsFile([
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => (string) ($config['port'] ?? '3306'),
            'user' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
        ]);

        try {
            $command = sprintf(
                'mysql --defaults-extra-file=%s %s < %s',
                escapeshellarg($defaultsFile),
                escapeshellarg((string) $config['database']),
                escapeshellarg($absolutePath)
            );

            $result = Process::timeout($this->processTimeout())->run(['bash', '-lc', $command]);

            if (! $result->successful()) {
                throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'mysql restore failed.');
            }
        } finally {
            @unlink($defaultsFile);
            DB::purge('mysql');
        }
    }

    protected function dumpPgsql(string $absolutePath): string
    {
        if ($this->preferredNativeToolsEnabled() && $this->binaryAvailable('pg_dump')) {
            $this->dumpPgsqlNative($absolutePath);

            return $this->usesCompression() ? 'pg_dump + gzip' : 'pg_dump';
        }

        throw new RuntimeException('PostgreSQL backup requires pg_dump on the server.');
    }

    protected function dumpPgsqlNative(string $absolutePath): void
    {
        $config = config('database.connections.pgsql');

        if (empty($config['database'])) {
            throw new RuntimeException('PostgreSQL database name is not configured.');
        }

        $env = array_filter([
            'PGPASSWORD' => $config['password'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $dumpCommand = sprintf(
            'pg_dump --host=%s --port=%s --username=%s --format=plain --no-owner --no-privileges %s',
            escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($config['port'] ?? '5432')),
            escapeshellarg((string) ($config['username'] ?? 'postgres')),
            escapeshellarg((string) $config['database'])
        );

        if ($this->usesCompression()) {
            if (! $this->binaryAvailable('gzip')) {
                throw new RuntimeException('gzip is required for compressed backups but is not available.');
            }

            $command = sprintf('%s | gzip -c > %s', $dumpCommand, escapeshellarg($absolutePath));
        } else {
            $command = sprintf('%s > %s', $dumpCommand, escapeshellarg($absolutePath));
        }

        $result = Process::timeout($this->processTimeout())->env($env)->run(['bash', '-lc', $command]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'pg_dump failed.');
        }
    }

    protected function restorePgsql(string $absolutePath): void
    {
        $compressed = str_ends_with(strtolower($absolutePath), '.gz');

        if (! $this->preferredNativeToolsEnabled() || ! $this->binaryAvailable('psql')) {
            throw new RuntimeException('PostgreSQL restore requires psql on the server.');
        }

        $config = config('database.connections.pgsql');

        if (empty($config['database'])) {
            throw new RuntimeException('PostgreSQL database name is not configured.');
        }

        DB::disconnect('pgsql');

        $env = array_filter([
            'PGPASSWORD' => $config['password'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($compressed) {
            if (! $this->binaryAvailable('gzip')) {
                throw new RuntimeException('gzip is required to restore compressed PostgreSQL backups.');
            }

            $command = sprintf(
                'gzip -dc %s | psql --host=%s --port=%s --username=%s --dbname=%s',
                escapeshellarg($absolutePath),
                escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
                escapeshellarg((string) ($config['port'] ?? '5432')),
                escapeshellarg((string) ($config['username'] ?? 'postgres')),
                escapeshellarg((string) $config['database'])
            );
        } else {
            $command = sprintf(
                'psql --host=%s --port=%s --username=%s --dbname=%s --file=%s',
                escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
                escapeshellarg((string) ($config['port'] ?? '5432')),
                escapeshellarg((string) ($config['username'] ?? 'postgres')),
                escapeshellarg((string) $config['database']),
                escapeshellarg($absolutePath)
            );
        }

        $result = Process::timeout($this->processTimeout())->env($env)->run(['bash', '-lc', $command]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'psql restore failed.');
        }

        DB::purge('pgsql');
    }

    protected function dumpSqlite(string $absolutePath): ?string
    {
        $databasePath = (string) config('database.connections.sqlite.database');

        if ($databasePath === ':memory:') {
            throw new RuntimeException('In-memory SQLite databases cannot be backed up. Use a file-based SQLite database.');
        }

        if (! is_file($databasePath)) {
            throw new RuntimeException('SQLite database file not found.');
        }

        if (! copy($databasePath, $absolutePath)) {
            throw new RuntimeException('Failed to copy SQLite database file.');
        }

        return 'file-copy';
    }

    protected function restoreSqlite(string $absolutePath): void
    {
        $databasePath = (string) config('database.connections.sqlite.database');

        if ($databasePath === ':memory:') {
            throw new RuntimeException('In-memory SQLite databases cannot be restored.');
        }

        DB::disconnect('sqlite');
        DB::purge('sqlite');

        if (! copy($absolutePath, $databasePath)) {
            throw new RuntimeException('Failed to restore SQLite database file.');
        }
    }

    /**
     * @param  array{host: string, port: string, user: string, password: string}  $config
     */
    protected function writeMysqlDefaultsFile(array $config): string
    {
        $path = storage_path('app/private/'.self::BACKUP_DIR.'/mysql-client-'.uniqid('', true).'.cnf');
        File::ensureDirectoryExists(dirname($path));

        $contents = implode(PHP_EOL, [
            '[client]',
            'host='.addcslashes($config['host'], '"\\'),
            'port='.addcslashes($config['port'], '"\\'),
            'user='.addcslashes($config['user'], '"\\'),
            'password='.addcslashes($config['password'], '"\\'),
            '',
        ]);

        File::put($path, $contents);
        chmod($path, 0600);

        return $path;
    }

    protected function binaryAvailable(string $binary): bool
    {
        $result = Process::run(['bash', '-lc', 'command -v '.escapeshellarg($binary)]);

        return $result->successful();
    }

    protected function processTimeout(): int
    {
        return (int) config('database_backup.timeout', 3600);
    }

    protected function generateUploadedFilename(string $driver): string
    {
        do {
            $suffix = substr(uniqid(), -6);
            $filename = sprintf(
                'backup-%s-uploaded-%s-%s.%s',
                $driver,
                $suffix,
                now()->format('Y-m-d_His'),
                $this->sqlExtension(),
            );
        } while (Storage::disk('local')->exists(self::BACKUP_DIR.'/'.$filename));

        if (! $this->isValidBackupFilename($filename)) {
            throw new RuntimeException('Generated upload filename is invalid.');
        }

        return $filename;
    }

    protected function isGzipFile(string $path): bool
    {
        if ($path === '' || ! is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 2);
        fclose($handle);

        return $header === "\x1f\x8b";
    }

    protected function looksLikeSqlDump(string $path): bool
    {
        if ($path === '' || ! is_readable($path)) {
            return false;
        }

        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = (string) gzread($handle, 8192);
        gzclose($handle);

        if ($sample === '') {
            return false;
        }

        $normalized = strtolower($sample);

        return str_contains($normalized, 'create table')
            || str_contains($normalized, 'create database')
            || str_contains($normalized, 'insert into')
            || str_contains($normalized, 'postgresql')
            || str_contains($normalized, 'mysqldump');
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
