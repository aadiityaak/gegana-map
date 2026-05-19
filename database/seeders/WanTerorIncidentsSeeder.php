<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WanTerorIncidentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('wan_teror_incidents')) {
            return;
        }

        if (! $this->wilayahReady()) {
            return;
        }

        $desiredTotal = 220;
        $types = [
            'napiter',
            'ex-napiter',
            'jaringan-terorisme',
            'bullying-perundungan',
            'aksi-teror',
        ];
        $descriptionsByType = [
            'napiter' => [
                '<p>Data narapidana terorisme terverifikasi.</p><ul><li>Pendataan identitas</li><li>Pemetaan domisili</li></ul>',
                '<p>Update data napiter berdasarkan laporan lapangan.</p><p>Tindakan: validasi dan sinkronisasi.</p>',
            ],
            'ex-napiter' => [
                '<p>Data eks napiter dalam program monitoring.</p><p>Tindakan: pemantauan aktivitas dan koordinasi.</p>',
                '<p>Update data ex-napiter.</p><ul><li>Verifikasi domisili</li><li>Evaluasi pembinaan</li></ul>',
            ],
            'jaringan-terorisme' => [
                '<p>Informasi jaringan terorisme teridentifikasi.</p><p>Tindakan: analisis hubungan dan pemetaan wilayah.</p>',
                '<p>Pemetaan jaringan dan simpul wilayah.</p><ul><li>Analisis pola</li><li>Koordinasi intelijen</li></ul>',
            ],
            'bullying-perundungan' => [
                '<p>Laporan bullying/perundungan diterima.</p><p>Tindakan: klarifikasi awal dan pendampingan.</p>',
                '<p>Kasus perundungan terpantau.</p><ul><li>Koordinasi pihak terkait</li><li>Monitoring lanjutan</li></ul>',
            ],
            'aksi-teror' => [
                '<p>Indikasi aksi teror dilaporkan.</p><ul><li>Pengamanan lokasi</li><li>Koordinasi respons cepat</li></ul>',
                '<p>Aksi teror terjadi, dilakukan penanganan awal.</p><p>Tindakan: penyisiran dan pengamanan TKP.</p>',
            ],
        ];

        $schema = DB::getSchemaBuilder();
        $now = now();
        $existingCount = DB::table('wan_teror_incidents')->count();

        if ($schema->hasColumn('wan_teror_incidents', 'description')) {
            DB::table('wan_teror_incidents')
                ->whereNull('description')
                ->update(['description' => $descriptionsByType['napiter'][0], 'updated_at' => $now]);
        }

        if ($schema->hasColumn('wan_teror_incidents', 'photos')) {
            DB::table('wan_teror_incidents')
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

        $currentCounts = DB::table('wan_teror_incidents')
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

        DB::table('wan_teror_incidents')->insert($rows);
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
        array $descriptionsByType,
    ): ?array {
        $location = $this->pickLocationInProvince($provinceId);
        if (! $location) {
            return null;
        }

        $incidentType = (string) $this->randomElement($types);
        $descriptionPool = $descriptionsByType[$incidentType] ?? [];
        $description = count($descriptionPool) > 0
            ? (string) $this->randomElement($descriptionPool)
            : null;

        $createdAt = now()->subDays($this->randomBetween(0, 240));

        return [
            'incident_type' => $incidentType,
            'finding_type' => null,
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
