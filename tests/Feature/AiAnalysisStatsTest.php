<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Memastikan endpoint analisa AI mengembalikan (dan menyimpan) data statistik
 * yang dipakai untuk grafik: tren bulanan, area rawan, distribusi kategori, KPI.
 * Panggilan ke AI dipalsukan (Http::fake) supaya tidak menyentuh layanan sungguhan.
 */
class AiAnalysisStatsTest extends TestCase
{
    private ?string $settingsAsli = null;

    protected function setUp(): void
    {
        parent::setUp();

        // rute AI berada di grup 'auth + verified'; untuk uji statistik kita lewati middleware
        $this->withoutMiddleware();

        Schema::dropIfExists('jibom_incidents');
        Schema::dropIfExists('reg_provinces');
        Schema::dropIfExists('ai_analysis_history');

        Schema::create('reg_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('jibom_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_type')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->text('incident_location')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_analysis_history', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('action');
            $table->string('period');
            $table->integer('total_data')->default(0);
            $table->text('prompt')->nullable();
            $table->text('result')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });

        DB::table('reg_provinces')->insert([
            ['id' => 1, 'name' => 'Jawa Barat'],
            ['id' => 2, 'name' => 'Jawa Timur'],
            ['id' => 3, 'name' => 'Papua'],
        ]);

        $catat = function (int $provinsi, ?string $tipe, \Carbon\CarbonInterface $kapan) {
            DB::table('jibom_incidents')->insert([
                'incident_type' => $tipe,
                'province_id' => $provinsi,
                'incident_location' => 'lokasi uji',
                'created_at' => $kapan,
            ]);
        };

        // periode berjalan (6 bulan terakhir): 7 kejadian
        $catat(1, 'ledakan', now()->subMonths(1)->startOfMonth()->addDays(2));
        $catat(1, 'ledakan', now()->subMonths(1)->startOfMonth()->addDays(3));
        $catat(1, 'ledakan', now()->subMonths(1)->startOfMonth()->addDays(4));
        $catat(1, 'ledakan', now()->startOfMonth()->addDays(1));
        $catat(1, 'ledakan', now()->startOfMonth()->addDays(2));
        $catat(2, 'napiter', now()->startOfMonth()->addDays(3));
        $catat(0, 'ledakan', now()->startOfMonth()->addDays(4)); // provinsi kosong

        // periode sebelumnya (6 bulan lebih awal): 4 kejadian
        $catat(1, 'ledakan', now()->subMonths(8)->startOfMonth()->addDays(2));
        $catat(2, 'ledakan', now()->subMonths(8)->startOfMonth()->addDays(3));
        $catat(3, 'napiter', now()->subMonths(9)->startOfMonth()->addDays(4));
        $catat(1, 'ledakan', now()->subMonths(10)->startOfMonth()->addDays(5));

        // kredensial AI palsu (file asli dipulihkan pada tearDown)
        $path = storage_path('app/ai/settings.json');
        File::ensureDirectoryExists(dirname($path));
        $this->settingsAsli = File::exists($path) ? (string) File::get($path) : null;
        File::put($path, json_encode([
            'endpoint' => 'https://ai-uji.test/v1/chat/completions',
            'api_key' => 'kunci-uji',
            'model' => 'model-uji',
        ]));

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => 'Analisa uji: ledakan didominasi Jawa Barat.']]],
            ], 200),
        ]);
    }

    protected function tearDown(): void
    {
        $path = storage_path('app/ai/settings.json');

        if ($this->settingsAsli === null) {
            File::delete($path);
        } else {
            File::put($path, $this->settingsAsli);
        }

        parent::tearDown();
    }

    public function test_endpoint_mengembalikan_statistik_untuk_grafik(): void
    {
        $res = $this->getJson('/api/ai/analyze/jibom?action=analisa&period=6months');

        $res->assertOk();
        $res->assertJsonPath('result', 'Analisa uji: ledakan didominasi Jawa Barat.');

        $stats = $res->json('stats');

        $this->assertSame(7, $stats['kpi']['total']);
        $this->assertSame(2, $stats['kpi']['provinsi']);
        $this->assertSame(4, $stats['kpi']['sebelumnya']);
        $this->assertSame(75.0, (float) $stats['kpi']['delta_persen']);
        $this->assertSame(1.2, (float) $stats['kpi']['rata_per_bulan']);

        // tren bulanan: 6 titik, jumlahnya sama dengan total
        $this->assertCount(6, $stats['tren']);
        $this->assertSame(7, array_sum(array_column($stats['tren'], 'value')));

        // area rawan terurut menurun
        $this->assertSame('Jawa Barat', $stats['provinsi'][0]['label']);
        $this->assertSame(5, $stats['provinsi'][0]['value']);
        $this->assertContains('Belum diketahui', array_column($stats['provinsi'], 'label'));

        // distribusi kategori
        $this->assertSame('Ledakan', $stats['tipe'][0]['label']);
        $this->assertSame(6, $stats['tipe'][0]['value']);
        $this->assertSame('Napiter', $stats['tipe'][1]['label']);
    }

    public function test_statistik_tersimpan_dan_tersedia_di_riwayat(): void
    {
        $this->getJson('/api/ai/analyze/jibom?action=analisa&period=1year')->assertOk();
        $this->getJson('/api/ai/analyze/jibom?action=prediksi&period=1year')->assertOk();

        $baris = DB::table('ai_analysis_history')->orderBy('id')->get();
        $this->assertCount(2, $baris);

        $tersimpan = DB::table('ai_analysis_history')->where('action', 'prediksi')->first();
        $stats = json_decode($tersimpan->stats, true);

        $this->assertSame('prediksi', $tersimpan->action);
        // periode 1 tahun mencakup 7 kejadian berjalan + 4 kejadian lama
        $this->assertSame(11, $stats['kpi']['total']);
        $this->assertCount(12, $stats['tren']);
        $this->assertSame(11, array_sum(array_column($stats['tren'], 'value')));

        // endpoint riwayat per modul menyertakan kolom stats
        $riwayat = $this->getJson('/api/ai/history/jibom');
        $riwayat->assertOk();
        $this->assertSame(11, $riwayat->json('data.0.stats.kpi.total'));

        // endpoint riwayat semua modul juga
        $semua = $this->getJson('/api/ai/all-history?view=ai');
        $semua->assertOk();
        $this->assertSame(11, $semua->json('data.0.stats.kpi.total'));
    }

    public function test_periode_tanpa_data_tetap_mengembalikan_statistik_kosong(): void
    {
        DB::table('jibom_incidents')->delete();

        $res = $this->getJson('/api/ai/analyze/jibom?action=antisipasi&period=1month');
        $res->assertOk();
        $this->assertSame(0, $res->json('stats.kpi.total'));
        $this->assertSame([], $res->json('stats.provinsi'));
        $this->assertCount(1, $res->json('stats.tren'));
        $this->assertSame(0, $res->json('stats.tren.0.value'));
    }
}
