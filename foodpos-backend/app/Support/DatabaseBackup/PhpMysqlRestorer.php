<?php

namespace App\Support\DatabaseBackup;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PhpMysqlRestorer
{
    /**
     * Restore a plain SQL or .sql.gz dump produced by PhpMysqlDumper or compatible tools.
     */
    public function restore(string $absolutePath): void
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Backup file not found.');
        }

        DB::disconnect('mysql');

        $handle = $this->openReader($absolutePath);
        $compressed = str_ends_with(strtolower($absolutePath), '.gz');

        try {
            $statement = '';

            while (($line = $this->readLine($handle, $compressed)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                    continue;
                }

                $statement .= $line;

                if (! str_ends_with(rtrim($line), ';')) {
                    continue;
                }

                DB::connection('mysql')->unprepared($statement);
                $statement = '';
            }

            if (trim($statement) !== '') {
                DB::connection('mysql')->unprepared($statement);
            }
        } finally {
            $this->closeReader($handle, $compressed);
            DB::purge('mysql');
        }
    }

    /**
     * @return resource
     */
    protected function openReader(string $absolutePath)
    {
        if (str_ends_with(strtolower($absolutePath), '.gz')) {
            $handle = gzopen($absolutePath, 'rb');
        } else {
            $handle = fopen($absolutePath, 'rb');
        }

        if ($handle === false) {
            throw new RuntimeException('Unable to open backup file for reading.');
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    protected function readLine($handle, bool $compressed): string|false
    {
        if ($compressed) {
            return gzgets($handle);
        }

        return fgets($handle);
    }

    /**
     * @param  resource  $handle
     */
    protected function closeReader($handle, bool $compressed): void
    {
        if ($compressed) {
            gzclose($handle);

            return;
        }

        fclose($handle);
    }
}
