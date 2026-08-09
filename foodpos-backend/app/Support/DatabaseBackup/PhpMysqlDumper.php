<?php

namespace App\Support\DatabaseBackup;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PhpMysqlDumper
{
    public function __construct(
        protected int $chunkRows = 500,
    ) {}

    /**
     * Stream a plain SQL dump to the given path (optionally gzip compressed).
     */
    public function dump(string $absolutePath, bool $compress = true): void
    {
        $database = (string) config('database.connections.mysql.database');
        if ($database === '') {
            throw new RuntimeException('MySQL database name is not configured.');
        }

        $handle = $this->openWriter($absolutePath, $compress);

        try {
            $this->writeLine($handle, '-- FoodPOS PHP database backup', $compress);
            $this->writeLine($handle, '-- Generated: '.now()->toDateTimeString(), $compress);
            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=0;', $compress);
            $this->writeLine($handle, 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";', $compress);
            $this->writeLine($handle, 'SET time_zone = "+00:00";', $compress);
            $this->writeLine($handle, '', $compress);

            foreach ($this->tables() as $table) {
                $this->dumpTable($handle, $table, $compress);
            }

            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=1;', $compress);
        } finally {
            $this->closeWriter($handle, $compress);
        }
    }

    /**
     * @return list<string>
     */
    protected function tables(): array
    {
        $database = (string) config('database.connections.mysql.database');

        return collect(DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))
            ->map(function (object $row) use ($database) {
                $property = 'Tables_in_'.$database;

                return (string) ($row->{$property} ?? '');
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  resource  $handle
     */
    protected function dumpTable($handle, string $table, bool $compress): void
    {
        $this->writeLine($handle, '--', $compress);
        $this->writeLine($handle, '-- Table structure for `'.$table.'`', $compress);
        $this->writeLine($handle, '--', $compress);
        $this->writeLine($handle, 'DROP TABLE IF EXISTS `'.$table.'`;', $compress);

        $create = DB::select('SHOW CREATE TABLE `'.$table.'`')[0] ?? null;
        $createSql = $create->{'Create Table'} ?? null;
        if (! is_string($createSql) || $createSql === '') {
            throw new RuntimeException('Unable to read CREATE TABLE for ['.$table.'].');
        }

        $this->writeLine($handle, $createSql.';', $compress);
        $this->writeLine($handle, '', $compress);

        $totalRows = (int) DB::table($table)->count();
        if ($totalRows === 0) {
            return;
        }

        $this->writeLine($handle, '--', $compress);
        $this->writeLine($handle, '-- Data for table `'.$table.'` ('.$totalRows.' rows)', $compress);
        $this->writeLine($handle, '--', $compress);

        $columns = $this->columnNames($table);
        $columnList = implode('`, `', $columns);
        $offset = 0;

        while ($offset < $totalRows) {
            $rows = DB::table($table)
                ->offset($offset)
                ->limit($this->chunkRows)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $this->quoteValue($row->{$column} ?? null);
                }

                $this->writeLine(
                    $handle,
                    'INSERT INTO `'.$table.'` (`'.$columnList.'`) VALUES ('.implode(', ', $values).');',
                    $compress
                );
            }

            $offset += $this->chunkRows;
        }

        $this->writeLine($handle, '', $compress);
    }

    /**
     * @return list<string>
     */
    protected function columnNames(string $table): array
    {
        return collect(DB::select('SHOW COLUMNS FROM `'.$table.'`'))
            ->map(fn (object $column) => (string) $column->Field)
            ->all();
    }

    protected function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return DB::getPdo()->quote($value->format('Y-m-d H:i:s'));
        }

        return DB::getPdo()->quote((string) $value);
    }

    /**
     * @return resource
     */
    protected function openWriter(string $absolutePath, bool $compress)
    {
        if ($compress) {
            $handle = gzopen($absolutePath, 'wb9');
        } else {
            $handle = fopen($absolutePath, 'wb');
        }

        if ($handle === false) {
            throw new RuntimeException('Unable to open backup file for writing.');
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    protected function closeWriter($handle, bool $compress): void
    {
        if ($compress) {
            gzclose($handle);
        } else {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    protected function writeLine($handle, string $line, bool $compress): void
    {
        $payload = $line.PHP_EOL;

        if ($compress) {
            gzwrite($handle, $payload);

            return;
        }

        fwrite($handle, $payload);
    }
}
