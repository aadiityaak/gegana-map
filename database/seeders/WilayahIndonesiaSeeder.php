<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WilayahIndonesiaSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        if (DB::table('reg_provinces')->count() > 0) {
            return;
        }

        $path = env('WILAYAH_SQL_PATH', 'C:\Users\ASUS\Downloads\wilayah_indonesia (1).sql');
        if (! is_string($path) || $path === '' || ! file_exists($path)) {
            return;
        }

        $sql = file_get_contents($path);
        if (! is_string($sql) || $sql === '') {
            return;
        }

        DB::transaction(function () use ($sql) {
            $this->seedTable($sql, 'reg_provinces', ['id', 'name'], 2);
            $this->seedTable($sql, 'reg_regencies', ['id', 'province_id', 'name'], 3);
            $this->seedTable($sql, 'reg_districts', ['id', 'regency_id', 'name'], 3);
            $this->seedTable($sql, 'reg_villages', ['id', 'district_id', 'name'], 3);
        });
    }

    private function tablesExist(): bool
    {
        $tables = ['reg_provinces', 'reg_regencies', 'reg_districts', 'reg_villages'];
        foreach ($tables as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                return false;
            }
        }
        return true;
    }

    private function seedTable(string $sql, string $table, array $columns, int $valueCount): void
    {
        $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s+VALUES\s*(.+?);/s';
        if (! preg_match($pattern, $sql, $m)) {
            return;
        }

        $tuples = $this->parseTuples((string) $m[1], $valueCount);
        if (count($tuples) === 0) {
            return;
        }

        $batch = [];
        foreach ($tuples as $tuple) {
            $row = [];
            foreach ($columns as $i => $col) {
                $row[$col] = $tuple[$i] ?? null;
            }
            $batch[] = $row;

            if (count($batch) >= 500) {
                DB::table($table)->insert($batch);
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            DB::table($table)->insert($batch);
        }
    }

    private function parseTuples(string $input, int $valueCount): array
    {
        $len = strlen($input);
        $i = 0;
        $results = [];

        while ($i < $len) {
            while ($i < $len && $input[$i] !== '(') {
                $i++;
            }
            if ($i >= $len) {
                break;
            }

            $i++;
            $values = [];
            for ($v = 0; $v < $valueCount; $v++) {
                while ($i < $len && ($input[$i] === ' ' || $input[$i] === "\n" || $input[$i] === "\r" || $input[$i] === "\t")) {
                    $i++;
                }

                if ($i >= $len || $input[$i] !== "'") {
                    $values = [];
                    break;
                }

                $i++;
                $buf = '';
                while ($i < $len) {
                    $ch = $input[$i];
                    if ($ch === "\\") {
                        $i++;
                        if ($i < $len) {
                            $buf .= $input[$i];
                            $i++;
                        }
                        continue;
                    }
                    if ($ch === "'") {
                        $i++;
                        break;
                    }
                    $buf .= $ch;
                    $i++;
                }

                $values[] = $buf;

                while ($i < $len && ($input[$i] === ' ' || $input[$i] === "\n" || $input[$i] === "\r" || $input[$i] === "\t")) {
                    $i++;
                }

                if ($v < $valueCount - 1) {
                    if ($i < $len && $input[$i] === ',') {
                        $i++;
                    }
                }
            }

            while ($i < $len && $input[$i] !== ')') {
                $i++;
            }
            if ($i < $len && $input[$i] === ')') {
                $i++;
            }

            if (count($values) === $valueCount) {
                $results[] = $values;
            }
        }

        return $results;
    }
}

