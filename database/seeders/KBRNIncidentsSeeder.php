<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KBRNIncidentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('kbrn_incidents')) {
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
                '<p>Laporan ancaman KBRN diterima.</p><ul><li>Verifikasi awal</li><li>Koordinasi pengamanan</li></ul>',
                '<p>Informasi ancaman KBRN masuk dari masyarakat.</p><p>Tindakan: penilaian risiko dan pengamanan area.</p>',
            ],
            'temuan' => [
                '<p>Temuan material diduga berbahaya.</p><p>Tindakan: isolasi lokasi dan pemeriksaan awal.</p>',
                '<p>Temuan KBRN di lokasi publik.</p><ul><li>Sterilisasi area</li><li>Koordinasi tim terkait</li></ul>',
            ],
            'ledakan' => [
                '<p>Ledakan dilaporkan di sekitar lokasi.</p><p>Tindakan: penyisiran dan pengamanan TKP.</p>',
                '<p>Insiden ledakan terkait bahan berbahaya.</p><p>Perlu pendalaman sumber pemicu.</p>',
            ],
        ];

        $schema = DB::getSchemaBuilder();
        $columns = [
            'description' => $schema->hasColumn('kbrn_incidents', 'description'),
            'photos' => $schema->hasColumn('kbrn_incidents', 'photos'),
            'latitude' => $schema->hasColumn('kbrn_incidents', 'latitude'),
            'longitude' => $schema->hasColumn('kbrn_incidents', 'longitude'),
            'news_source' => $schema->hasColumn('kbrn_incidents', 'news_source'),
            'news_url' => $schema->hasColumn('kbrn_incidents', 'news_url'),
        ];
        $now = now();
        $existingCount = DB::table('kbrn_incidents')->count();

        if ($columns['description']) {
            DB::table('kbrn_incidents')
                ->whereNull('description')
                ->update([
                    'description' => $descriptionsByType['ancaman'][0],
                    'updated_at' => $now,
                ]);
        }

        if ($columns['photos']) {
            DB::table('kbrn_incidents')
                ->whereNull('photos')
                ->update(['photos' => json_encode([]), 'updated_at' => $now]);
        }

        $galleryPaths = $columns['photos'] ? $this->ensureGallerySeedImages('kbrn') : [];
        if ($columns['photos'] && count($galleryPaths) > 0) {
            $this->backfillGalleryPhotos('kbrn_incidents', $galleryPaths, $now);
        }

        if ($columns['news_source']) {
            DB::table('kbrn_incidents')
                ->whereNull('news_source')
                ->orWhere('news_source', '')
                ->update(['news_source' => 'offline', 'updated_at' => $now]);
        }

        if ($columns['news_url'] && $columns['news_source']) {
            DB::table('kbrn_incidents')
                ->where('news_source', 'offline')
                ->whereNotNull('news_url')
                ->update(['news_url' => null, 'updated_at' => $now]);
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

        $currentCounts = DB::table('kbrn_incidents')
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
                    columns: $columns,
                    galleryPaths: $galleryPaths,
                );

                if (! $row) {
                    $currentCounts[$provinceId] = (int) ($currentCounts[$provinceId] ?? 0) + 1000;
                    continue;
                }

                $rows[] = $row;
                $currentCounts[$provinceId] = (int) ($currentCounts[$provinceId] ?? 0) + 1;
            }
        }

        DB::table('kbrn_incidents')->insert($rows);
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
        array $columns,
        array $galleryPaths,
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

        $newsSource = 'offline';
        $newsUrl = null;
        if (($columns['news_source'] ?? false) || ($columns['news_url'] ?? false)) {
            $newsRoll = $this->randomBetween(1, 100);
            $newsSource = $newsRoll <= 20 ? 'online' : 'offline';
            $newsUrl = $newsSource === 'online'
                ? $this->randomNewsUrl('kbrn', $incidentType)
                : null;
        }

        $latitude = null;
        $longitude = null;
        if (($columns['latitude'] ?? false) && ($columns['longitude'] ?? false)) {
            $coordRoll = $this->randomBetween(1, 100);
            if ($coordRoll <= 70) {
                $latitude = $this->randomFloat(-11.2, 6.9, 6);
                $longitude = $this->randomFloat(95.0, 141.0, 6);
            }
        }

        $photos = [];
        if (($columns['photos'] ?? false) && count($galleryPaths) > 0) {
            $photoRoll = $this->randomBetween(1, 100);
            if ($photoRoll <= 65) {
                $photoCount = $this->randomBetween(1, 3);
                $photos = $this->randomUniqueSubset($galleryPaths, $photoCount);
            }
        }

        $createdAt = now()->subDays($this->randomBetween(0, 180));

        $row = [
            'incident_type' => $incidentType,
            'finding_type' => $findingType,
            'province_id' => $location->province_id,
            'regency_id' => $location->regency_id,
            'district_id' => $location->district_id,
            'village_id' => $location->village_id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        if ($columns['description'] ?? false) {
            $row['description'] = $description;
        }
        if ($columns['photos'] ?? false) {
            $row['photos'] = json_encode($photos);
        }
        if ($columns['latitude'] ?? false) {
            $row['latitude'] = $latitude;
        }
        if ($columns['longitude'] ?? false) {
            $row['longitude'] = $longitude;
        }
        if ($columns['news_source'] ?? false) {
            $row['news_source'] = $newsSource;
        }
        if ($columns['news_url'] ?? false) {
            $row['news_url'] = $newsUrl;
        }

        return $row;
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

    private function randomFloat(float $min, float $max, int $decimals = 6): float
    {
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        $scale = 10 ** max(0, $decimals);
        $minInt = (int) round($min * $scale);
        $maxInt = (int) round($max * $scale);
        $n = random_int($minInt, $maxInt);

        return $n / $scale;
    }

    private function randomNewsUrl(string $module, string $incidentType): string
    {
        $id = $this->randomBetween(10000, 99999);
        $slug = $module . '-' . $incidentType . '-' . $id;

        return 'https://example.com/news/' . $slug;
    }

    private function ensureGallerySeedImages(string $module): array
    {
        $disk = Storage::disk('public');
        $paths = [];

        $sourcePath = base_path('public/branding/gegana-fav.png');
        $bytes = null;
        if (is_string($sourcePath) && $sourcePath !== '' && file_exists($sourcePath)) {
            $read = file_get_contents($sourcePath);
            if (is_string($read) && $read !== '') {
                $bytes = $read;
            }
        }
        if (! is_string($bytes) || $bytes === '') {
            $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/6X8pJQAAAAASUVORK5CYII=');
        }

        for ($i = 1; $i <= 6; $i++) {
            $path = "seed/{$module}/photo-{$i}.png";
            if (! $disk->exists($path)) {
                $disk->put($path, $bytes);
            }
            $paths[] = $path;
        }

        return $paths;
    }

    private function randomUniqueSubset(array $values, int $take): array
    {
        $values = array_values(array_filter($values, fn ($v) => is_string($v) && $v !== ''));
        $count = count($values);
        if ($count === 0 || $take <= 0) {
            return [];
        }

        $take = min($take, $count);
        $picked = [];

        while (count($picked) < $take) {
            $v = (string) $this->randomElement($values);
            $picked[$v] = true;
        }

        return array_keys($picked);
    }

    private function backfillGalleryPhotos(string $table, array $galleryPaths, mixed $now): void
    {
        $ids = DB::table($table)
            ->select(['id'])
            ->whereNull('photos')
            ->orWhere('photos', '')
            ->orWhere('photos', '[]')
            ->limit(160)
            ->pluck('id')
            ->all();

        foreach ($ids as $id) {
            $photoCount = $this->randomBetween(1, 3);
            $photos = $this->randomUniqueSubset($galleryPaths, $photoCount);
            if (count($photos) === 0) {
                continue;
            }

            DB::table($table)
                ->where('id', $id)
                ->update([
                    'photos' => json_encode($photos),
                    'updated_at' => $now,
                ]);
        }
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
