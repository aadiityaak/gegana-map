<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JibomIncidentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('jibom_incidents')) {
            return;
        }

        if (! $this->wilayahReady()) {
            return;
        }

        $desiredTotal = 180;
        $findingTypes = ['bom-militer', 'bom-rakitan', 'bom-ikan', 'petasan', 'lainnya'];
        $types = ['ancaman', 'temuan', 'ledakan'];
        $descriptionsByType = [
            'ancaman' => [
                '<p>Laporan ancaman diterima dari masyarakat.</p><ul><li>Koordinasi awal</li><li>Pengamanan area</li></ul>',
                '<p>Informasi ancaman masuk melalui kanal pengaduan.</p><p>Tindakan: verifikasi dan penilaian risiko.</p>',
            ],
            'temuan' => [
                '<p>Objek mencurigakan ditemukan di lokasi.</p><p>Tindakan: pemeriksaan dan sterilisasi.</p>',
                '<p>Temuan benda diduga bom.</p><ul><li>Isolasi lokasi</li><li>Koordinasi EOD</li></ul>',
            ],
            'ledakan' => [
                '<p>Terjadi ledakan, tidak ada korban jiwa.</p><p>Perlu pendalaman sumber pemicu.</p>',
                '<p>Ledakan dilaporkan warga sekitar.</p><p>Tindakan: penyisiran dan pengamanan TKP.</p>',
            ],
        ];

        $schema = DB::getSchemaBuilder();
        $now = now();
        $existingCount = DB::table('jibom_incidents')->count();

        if ($schema->hasColumn('jibom_incidents', 'description')) {
            DB::table('jibom_incidents')
                ->whereNull('description')
                ->update([
                    'description' => $descriptionsByType['ancaman'][0],
                    'updated_at' => $now,
                ]);
        }

        if ($schema->hasColumn('jibom_incidents', 'photos')) {
            DB::table('jibom_incidents')
                ->whereNull('photos')
                ->update(['photos' => json_encode([]), 'updated_at' => $now]);
        }

        $need = max(0, $desiredTotal - $existingCount);
        if ($need <= 0) {
            return;
        }

        $allProvinceIds = DB::table('reg_provinces')->pluck('id')->all();
        if (count($allProvinceIds) === 0) return;

        $rows = [];

        $existingProvinces = DB::table('jibom_incidents')
            ->distinct()
            ->pluck('province_id')
            ->all();
        $existingProvinceMap = array_fill_keys($existingProvinces, true);
        $missingProvinceIds = array_values(array_filter(
            $allProvinceIds,
            fn($id) => ! isset($existingProvinceMap[$id]),
        ));

        foreach ($missingProvinceIds as $provinceId) {
            if (count($rows) >= $need) break;
            $row = $this->makeIncidentRow(
                provinceId: (string) $provinceId,
                types: $types,
                findingTypes: $findingTypes,
                descriptionsByType: $descriptionsByType,
            );
            if ($row) {
                $rows[] = $row;
            }
        }

        while (count($rows) < $need) {
            $provinceId = (string) $this->randomElement($allProvinceIds);
            $row = $this->makeIncidentRow(
                provinceId: $provinceId,
                types: $types,
                findingTypes: $findingTypes,
                descriptionsByType: $descriptionsByType,
            );
            if ($row) {
                $rows[] = $row;
            }
        }

        DB::table('jibom_incidents')->insert($rows);
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
        if (! $regencyId) return null;

        $districtId = DB::table('reg_districts')
            ->where('regency_id', $regencyId)
            ->inRandomOrder()
            ->value('id');
        if (! $districtId) return null;

        $villageId = DB::table('reg_villages')
            ->where('district_id', $districtId)
            ->inRandomOrder()
            ->value('id');
        if (! $villageId) return null;

        return (object) [
            'village_id' => (string) $villageId,
            'district_id' => (string) $districtId,
            'regency_id' => (string) $regencyId,
            'province_id' => $provinceId,
        ];
    }
}
