<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KwrnIncidentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('kwrn_incidents')) {
            return;
        }

        if (! $this->wilayahReady()) {
            return;
        }

        $desiredTotal = 180;
        $findingTypes = ['kimia', 'biologi', 'radioaktif', 'nuklir', 'lainnya'];
        $types = ['ancaman', 'temuan', 'ledakan'];
        $descriptionsByType = [
            'ancaman' => [
                '<p>Laporan ancaman KWRN diterima.</p><ul><li>Verifikasi awal</li><li>Koordinasi pengamanan</li></ul>',
                '<p>Informasi ancaman KWRN masuk dari masyarakat.</p><p>Tindakan: penilaian risiko dan pengamanan area.</p>',
            ],
            'temuan' => [
                '<p>Temuan material diduga berbahaya.</p><p>Tindakan: isolasi lokasi dan pemeriksaan awal.</p>',
                '<p>Temuan KWRN di lokasi publik.</p><ul><li>Sterilisasi area</li><li>Koordinasi tim terkait</li></ul>',
            ],
            'ledakan' => [
                '<p>Ledakan dilaporkan di sekitar lokasi.</p><p>Tindakan: penyisiran dan pengamanan TKP.</p>',
                '<p>Insiden ledakan terkait bahan berbahaya.</p><p>Perlu pendalaman sumber pemicu.</p>',
            ],
        ];

        $schema = DB::getSchemaBuilder();
        $now = now();
        $existingCount = DB::table('kwrn_incidents')->count();

        if ($schema->hasColumn('kwrn_incidents', 'description')) {
            DB::table('kwrn_incidents')
                ->whereNull('description')
                ->update([
                    'description' => $descriptionsByType['ancaman'][0],
                    'updated_at' => $now,
                ]);
        }

        if ($schema->hasColumn('kwrn_incidents', 'photos')) {
            DB::table('kwrn_incidents')
                ->whereNull('photos')
                ->update(['photos' => json_encode([]), 'updated_at' => $now]);
        }

        $need = max(0, $desiredTotal - $existingCount);
        if ($need <= 0) {
            return;
        }

        $allProvinceIds = DB::table('reg_provinces')->pluck('id')->all();
        if (count($allProvinceIds) === 0) {
            return;
        }

        $rows = [];

        $currentCounts = DB::table('kwrn_incidents')
            ->select(['province_id', DB::raw('count(*) as c')])
            ->groupBy('province_id')
            ->pluck('c', 'province_id')
            ->all();

        $orderedProvinceIds = array_values($allProvinceIds);

        while (count($rows) < $need) {
            usort($orderedProvinceIds, function ($a, $b) use (&$currentCounts) {
                $ca = (int) ($currentCounts[(string) $a] ?? 0);
                $cb = (int) ($currentCounts[(string) $b] ?? 0);
                if ($ca === $cb) {
                    return strcmp((string) $a, (string) $b);
                }
                return $ca <=> $cb;
            });

            foreach ($orderedProvinceIds as $provinceId) {
                if (count($rows) >= $need) {
                    break 2;
                }

                $provinceId = (string) $provinceId;
                $row = $this->makeIncidentRow(
                    provinceId: $provinceId,
                    types: $types,
                    findingTypes: $findingTypes,
                    descriptionsByType: $descriptionsByType,
                );

                if (! $row) {
                    $currentCounts[$provinceId] = (int) ($currentCounts[$provinceId] ?? 0) + 1000;
                    continue;
                }

                $rows[] = $row;
                $currentCounts[$provinceId] = (int) ($currentCounts[$provinceId] ?? 0) + 1;
            }
        }

        DB::table('kwrn_incidents')->insert($rows);
    }

    private function wilayahReady(): bool
    {
        $tables = ['reg_provinces', 'reg_regencies', 'reg_districts', 'reg_villages'];
        foreach ($tables as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                return false;
            }
        }

        return DB::table('reg_villages')->count() > 0;
    }

    private function makeIncidentRow(
        string $provinceId,
        array $types,
        array $findingTypes,
        array $descriptionsByType,
    ): ?array {
        $location = $this->pickLocationInProvince($provinceId);
        if (! $location) {
            return null;
        }

        $roll = $this->randomBetween(1, 100);
        $incidentType = match (true) {
            $roll <= 35 => 'temuan',
            $roll <= 70 => 'ancaman',
            default => 'ledakan',
        };
        if (! in_array($incidentType, $types, true)) {
            $incidentType = (string) $this->randomElement($types);
        }

        $findingType = $incidentType === 'temuan'
            ? (string) $this->randomElement($findingTypes)
            : null;

        $descriptionPool = $descriptionsByType[$incidentType] ?? [];
        $description = count($descriptionPool) > 0
            ? (string) $this->randomElement($descriptionPool)
            : null;

        $createdAt = now()->subDays($this->randomBetween(0, 180));

        return [
            'incident_type' => $incidentType,
            'finding_type' => $findingType,
            'description' => $description,
            'photos' => json_encode([]),
            'province_id' => $location->province_id,
            'regency_id' => $location->regency_id,
            'district_id' => $location->district_id,
            'village_id' => $location->village_id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function randomBetween(int $min, int $max): int
    {
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
        return random_int($min, $max);
    }

    private function randomElement(array $values): mixed
    {
        $count = count($values);
        if ($count === 0) {
            return null;
        }
        $index = random_int(0, $count - 1);
        return $values[$index];
    }

    private function pickLocationInProvince(string $provinceId): ?object
    {
        $regencyId = DB::table('reg_regencies')
            ->where('province_id', $provinceId)
            ->inRandomOrder()
            ->value('id');
        if (! $regencyId) {
            return null;
        }

        $districtId = DB::table('reg_districts')
            ->where('regency_id', $regencyId)
            ->inRandomOrder()
            ->value('id');
        if (! $districtId) {
            return null;
        }

        $villageId = DB::table('reg_villages')
            ->where('district_id', $districtId)
            ->inRandomOrder()
            ->value('id');
        if (! $villageId) {
            return null;
        }

        return (object) [
            'village_id' => (string) $villageId,
            'district_id' => (string) $districtId,
            'regency_id' => (string) $regencyId,
            'province_id' => $provinceId,
        ];
    }
}
