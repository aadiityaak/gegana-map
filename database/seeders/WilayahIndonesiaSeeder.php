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

        $path = env('WILAYAH_SQL_PATH', 'C:\Users\ASUS\Downloads\wilayah_indonesia (1).sql');
        $hasSqlFile = is_string($path) && $path !== '' && file_exists($path);

        if (! $hasSqlFile) {
            $this->seedMinimalWilayah();
            return;
        }

        $sql = null;
        $seedSmallTables = function () use (&$sql, $path) {
            if ($sql === null) {
                $read = file_get_contents($path);
                $sql = is_string($read) ? $read : '';
            }
            if (! is_string($sql) || $sql === '') {
                return;
            }

            if (DB::table('reg_provinces')->count() === 0) {
                $this->seedTable($sql, 'reg_provinces', ['id', 'name'], 2);
            }
            if (DB::table('reg_regencies')->count() === 0) {
                $this->seedTable($sql, 'reg_regencies', ['id', 'province_id', 'name'], 3);
            }
            if (DB::table('reg_districts')->count() === 0) {
                $this->seedTable($sql, 'reg_districts', ['id', 'regency_id', 'name'], 3);
            }
        };

        DB::transaction(function () use ($seedSmallTables, $path) {
            $seedSmallTables();

            if (DB::table('reg_villages')->count() === 0) {
                $this->seedVillagesFromFile($path);
            }
        });
    }

    private function seedMinimalWilayah(): void
    {
        if (DB::table('reg_villages')->count() > 0) {
            return;
        }

        DB::transaction(function () {
            DB::table('reg_villages')->delete();
            DB::table('reg_districts')->delete();
            DB::table('reg_regencies')->delete();
            DB::table('reg_provinces')->delete();

            $provinces = [
                ['id' => '11', 'name' => 'Aceh'],
                ['id' => '12', 'name' => 'Sumatera Utara'],
                ['id' => '13', 'name' => 'Sumatera Barat'],
                ['id' => '14', 'name' => 'Riau'],
                ['id' => '15', 'name' => 'Jambi'],
                ['id' => '16', 'name' => 'Sumatera Selatan'],
                ['id' => '17', 'name' => 'Bengkulu'],
                ['id' => '18', 'name' => 'Lampung'],
                ['id' => '19', 'name' => 'Kepulauan Bangka Belitung'],
                ['id' => '21', 'name' => 'Kepulauan Riau'],
                ['id' => '31', 'name' => 'DKI Jakarta'],
                ['id' => '32', 'name' => 'Jawa Barat'],
                ['id' => '33', 'name' => 'Jawa Tengah'],
                ['id' => '34', 'name' => 'DI Yogyakarta'],
                ['id' => '35', 'name' => 'Jawa Timur'],
                ['id' => '36', 'name' => 'Banten'],
                ['id' => '51', 'name' => 'Bali'],
                ['id' => '52', 'name' => 'Nusa Tenggara Barat'],
                ['id' => '53', 'name' => 'Nusa Tenggara Timur'],
                ['id' => '61', 'name' => 'Kalimantan Barat'],
                ['id' => '62', 'name' => 'Kalimantan Tengah'],
                ['id' => '63', 'name' => 'Kalimantan Selatan'],
                ['id' => '64', 'name' => 'Kalimantan Timur'],
                ['id' => '65', 'name' => 'Kalimantan Utara'],
                ['id' => '71', 'name' => 'Sulawesi Utara'],
                ['id' => '72', 'name' => 'Sulawesi Tengah'],
                ['id' => '73', 'name' => 'Sulawesi Selatan'],
                ['id' => '74', 'name' => 'Sulawesi Tenggara'],
                ['id' => '75', 'name' => 'Gorontalo'],
                ['id' => '76', 'name' => 'Sulawesi Barat'],
                ['id' => '81', 'name' => 'Maluku'],
                ['id' => '82', 'name' => 'Maluku Utara'],
                ['id' => '91', 'name' => 'Papua'],
                ['id' => '92', 'name' => 'Papua Barat'],
                ['id' => '93', 'name' => 'Papua Selatan'],
                ['id' => '94', 'name' => 'Papua Tengah'],
                ['id' => '95', 'name' => 'Papua Pegunungan'],
                ['id' => '96', 'name' => 'Papua Barat Daya'],
            ];

            DB::table('reg_provinces')->insert($provinces);

            $regencies = [];
            $districts = [];
            $villages = [];

            foreach ($provinces as $prov) {
                $provinceId = (string) $prov['id'];
                $provinceName = (string) $prov['name'];

                $regencyId = $provinceId . '01';
                $districtId = $regencyId . '01';
                $villageId = $districtId . '0001';

                $regencies[] = [
                    'id' => $regencyId,
                    'province_id' => $provinceId,
                    'name' => 'Kabupaten Contoh ' . $provinceName,
                ];

                $districts[] = [
                    'id' => $districtId,
                    'regency_id' => $regencyId,
                    'name' => 'Kecamatan Contoh ' . $provinceName,
                ];

                $villages[] = [
                    'id' => $villageId,
                    'district_id' => $districtId,
                    'name' => 'Desa Contoh ' . $provinceName,
                ];
            }

            DB::table('reg_regencies')->insert($regencies);
            DB::table('reg_districts')->insert($districts);
            DB::table('reg_villages')->insert($villages);
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

    private function seedVillagesFromFile(string $path): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        $needle = 'INSERT INTO `reg_villages` VALUES';
        $found = false;

        $state = 'search';
        $valueCount = 3;
        $values = [];
        $buf = '';
        $inQuote = false;
        $escape = false;

        $batch = [];

        $flushBatch = function () use (&$batch) {
            if (count($batch) === 0) {
                return;
            }
            DB::table('reg_villages')->insert($batch);
            $batch = [];
        };

        $appendRow = function (array $tuple) use (&$batch, $flushBatch) {
            $batch[] = [
                'id' => $tuple[0],
                'district_id' => $tuple[1],
                'name' => $tuple[2],
            ];

            if (count($batch) >= 500) {
                $flushBatch();
            }
        };

        while (($line = fgets($handle)) !== false) {
            if (! $found) {
                if (strpos($line, $needle) === false) {
                    continue;
                }
                $found = true;

                $pos = strpos($line, 'VALUES');
                $line = $pos === false ? '' : substr($line, $pos + 5);
            }

            $len = strlen($line);
            for ($i = 0; $i < $len; $i++) {
                $ch = $line[$i];

                if ($state === 'search') {
                    if ($ch === '(') {
                        $values = [];
                        $state = 'expect_quote';
                    } elseif ($ch === ';') {
                        fclose($handle);
                        $flushBatch();
                        return;
                    }
                    continue;
                }

                if ($state === 'expect_quote') {
                    if ($ch === "'") {
                        $buf = '';
                        $inQuote = true;
                        $escape = false;
                        $state = 'in_value';
                    } elseif ($ch === ')') {
                        $state = 'search';
                    }
                    continue;
                }

                if ($state === 'in_value') {
                    if ($escape) {
                        $buf .= $ch;
                        $escape = false;
                        continue;
                    }
                    if ($ch === "\\") {
                        $escape = true;
                        continue;
                    }
                    if ($ch === "'") {
                        $inQuote = false;
                        $values[] = $buf;
                        $state = 'after_value';
                        continue;
                    }
                    $buf .= $ch;
                    continue;
                }

                if ($state === 'after_value') {
                    if ($ch === ',') {
                        if (count($values) < $valueCount) {
                            $state = 'expect_quote';
                        }
                        continue;
                    }
                    if ($ch === ')') {
                        if (count($values) === $valueCount) {
                            $appendRow($values);
                        }
                        $state = 'search';
                        continue;
                    }
                    if ($ch === ';') {
                        fclose($handle);
                        $flushBatch();
                        return;
                    }
                }
            }
        }

        fclose($handle);
        $flushBatch();
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
