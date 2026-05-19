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

        $findingTypes = ['bom-militer', 'bom-rakitan', 'bom-ikan', 'petasan', 'lainnya'];
        $types = ['ancaman', 'temuan', 'ledakan'];
        $descriptions = [
            '<p>Laporan awal diterima dari masyarakat.</p><ul><li>Koordinasi dengan tim lapangan</li><li>Pengamanan area</li></ul>',
            '<p>Objek mencurigakan ditemukan di lokasi.</p><p>Tindakan: pemeriksaan dan sterilisasi.</p>',
            '<p>Terjadi ledakan kecil, tidak ada korban jiwa.</p><p>Perlu pendalaman sumber pemicu.</p>',
        ];

        if (DB::table('jibom_incidents')->count() > 0) {
            $schema = DB::getSchemaBuilder();
            $now = now();

            if ($schema->hasColumn('jibom_incidents', 'description')) {
                DB::table('jibom_incidents')
                    ->whereNull('description')
                    ->update(['description' => $descriptions[0], 'updated_at' => $now]);
            }

            if ($schema->hasColumn('jibom_incidents', 'photos')) {
                DB::table('jibom_incidents')
                    ->whereNull('photos')
                    ->update(['photos' => json_encode([]), 'updated_at' => $now]);
            }

            return;
        }

        $villageRows = $this->pickVillages(30);
        if (count($villageRows) === 0) {
            return;
        }

        $now = now();
        $rows = [];
        $i = 0;

        foreach ($villageRows as $village) {
            $incidentType = $types[$i % count($types)];
            $findingType = $incidentType === 'temuan' ? $findingTypes[$i % count($findingTypes)] : null;
            $description = $descriptions[$i % count($descriptions)];

            $rows[] = [
                'incident_type' => $incidentType,
                'finding_type' => $findingType,
                'description' => $description,
                'photos' => json_encode([]),
                'province_id' => $village->province_id,
                'regency_id' => $village->regency_id,
                'district_id' => $village->district_id,
                'village_id' => $village->village_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $i++;
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

    private function pickVillages(int $limit): array
    {
        $rows = DB::table('reg_villages as v')
            ->join('reg_districts as d', 'd.id', '=', 'v.district_id')
            ->join('reg_regencies as r', 'r.id', '=', 'd.regency_id')
            ->select([
                'v.id as village_id',
                'v.district_id',
                'd.regency_id',
                'r.province_id',
            ])
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return $rows->all();
    }
}
