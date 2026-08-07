<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AiAndHermesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAiHistory();
        $this->seedHermesLogs();
    }

    private function seedAiHistory(): void
    {
        $modules = ['jibom', 'kbrn', 'wan-teror'];
        $actions = ['analisa', 'prediksi', 'antisipasi'];
        $periods = ['1month', '6months', '1year'];
        $results = $this->aiResults();

        $rows = [];
        $now = Carbon::now();

        for ($i = 0; $i < 20; $i++) {
            $module = $modules[array_rand($modules)];
            $action = $actions[array_rand($actions)];
            $period = $periods[array_rand($periods)];
            $totalData = match ($period) {
                '1month' => rand(10, 60),
                '6months' => rand(80, 350),
                '1year' => rand(200, 800),
            };
            $resultKey = $module . '_' . $action;
            $result = $results[$resultKey] ?? $results['wan-teror_prediksi'];

            $rows[] = [
                'module' => $module,
                'action' => $action,
                'period' => $period,
                'total_data' => $totalData,
                'prompt' => 'System prompt: Anda adalah asisten analis keamanan...',
                'result' => str_replace('{total}', (string) $totalData, $result),
                'created_at' => $now->copy()->subHours(rand(0, 72))->subMinutes(rand(0, 59)),
                'updated_at' => $now,
            ];
        }

        // Sort by created_at DESC
        usort($rows, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        DB::table('ai_analysis_history')->insert($rows);
    }

    private function seedHermesLogs(): void
    {
        $types = [
            'scan_start', 'search_start', 'search_done',
            'summarizing', 'summary_done',
            'insert', 'update', 'skip', 'error', 'info', 'scan_done',
        ];

        $titles = [
            'scan_start' => ['Memulai scan berita', 'Scan CNN Indonesia', 'Scan Kompas.com'],
            'search_start' => ['Mencari kejadian terorisme', 'Pencarian insiden JIBOM', 'Pencarian KBRN'],
            'search_done' => ['Ditemukan 5 artikel', 'Ditemukan 12 berita baru', 'Pencarian selesai'],
            'summarizing' => ['Merangkum artikel', 'Ekstraksi data kejadian', 'Analisis konten berita'],
            'summary_done' => ['Ringkasan selesai: 3 kejadian', 'Ringkasan selesai: 7 insiden', 'Analisis konten selesai'],
            'insert' => ['Data baru disimpan: Penyelundupan di Jakarta', 'Insiden baru: JIBOM Jawa Barat', 'KBRN insiden tersimpan'],
            'update' => ['Update data WAN TEROR #45', 'Perbarui status kejadian', 'Update metadata insiden'],
            'skip' => ['Artikel duplikat dilewati', 'Sudah ada di database', 'Bukan kejadian relevan'],
            'error' => ['Gagal fetch halaman', 'Timeout API', 'Parse error pada artikel'],
            'info' => ['Agent idle', 'Menunggu interval berikutnya', 'Cek database OK'],
            'scan_done' => ['Scan selesai: 15 artikel', 'Scan berita selesai', 'Cycle selesai: 8 diproses'],
        ];

        $messages = [
            'scan_start' => ['Melakukan scan berita dari 5 sumber.', 'Mulai scan periodik.', null],
            'search_start' => ['Keyword: terorisme, bom, penangkapan.', 'Query: insiden keamanan.', null],
            'search_done' => ['5 artikel baru ditemukan, 2 duplikat.', '12 hasil ditemukan.', null],
            'summarizing' => ['Memproses artikel...', 'Menggunakan model AI untuk ekstraksi.', null],
            'summary_done' => ['Lokasi: Jawa Barat, Tipe: Penyelundupan.', '3 kejadian berhasil diekstrak.', null],
            'insert' => ['Disimpan ke database.', 'ID record: 12345.', null],
            'update' => ['Field updated: status.', 'Metadata diperbarui.', null],
            'skip' => ['URL: https://example.com/article-1', 'Duplicate dengan record #42.', null],
            'error' => ['Connection refused.', 'HTTP 500 dari sumber.', 'Format JSON tidak valid.'],
            'info' => ['Semua sistem normal.', 'Database connection OK.', null],
            'scan_done' => ['Total: 15 artikel, 8 relevan, 5 disimpan.', 'Waktu: 2.3 detik.', null],
        ];

        $rows = [];
        $now = Carbon::now();

        for ($i = 0; $i < 40; $i++) {
            $type = $types[array_rand($types)];
            $titlePool = $titles[$type];
            $title = $titlePool[array_rand($titlePool)];
            $msgPool = $messages[$type];
            $message = $msgPool[array_rand($msgPool)];

            $metadata = null;
            if (in_array($type, ['search_done', 'insert', 'update', 'skip'])) {
                $metadata = json_encode([
                    'count' => rand(1, 15),
                    'source' => ['CNN', 'Kompas', 'Detik', 'Tribun'][array_rand(['CNN', 'Kompas', 'Detik', 'Tribun'])],
                ]);
            }
            if ($type === 'error') {
                $metadata = json_encode([
                    'status' => rand(400, 599),
                    'endpoint' => 'https://api.example.com/v1/fetch',
                ]);
            }

            $rows[] = [
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'metadata' => $metadata,
                'created_at' => $now->copy()->subMinutes(rand(0, 120))->subSeconds(rand(0, 59)),
            ];
        }

        // Sort by created_at DESC
        usort($rows, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        DB::table('hermes_agent_logs')->insert($rows);
    }

    private function aiResults(): array
    {
        return [
            'jibom_analisa' => "**Analisa Data JIBOM (6 Bulan Terakhir)**\n\nTotal {total} kejadian.\n\n**Pola Tipe**\n- 3 tipe dominan: penyelundupan (45), perampokan (30), pencurian (25) → 59.5% total.\n- Tipe lain 68 kejadian.\n\n**Tren Bulanan**\n- Jan: 22 → Feb: 28 → Mar: 35 → Apr: 40 (puncak) → Mei: 25 → Jun: 18.\n- Tren menurun setelah April, perlu investigasi penyebab.\n\n**Distribusi Geografis**\n- Jawa Barat (19)\n- DKI Jakarta (9)\n- Lampung (7)\n- Banten (6)\n- Jawa Tengah (5)\n\n**Temuan Penting**\n- Konsentrasi tinggi di Pulau Jawa.\n- Penurunan signifikan pasca April — perlu dicek apakah karena operasi atau kurang laporan.",

            'jibom_prediksi' => "**Prediksi Data JIBOM (6 Bulan Terakhir)**\n\nTotal {total} kejadian.\n\n**Prediksi**\nKejadian diperkirakan stabil di kisaran 25–35/bulan. Pola musiman terlihat dengan puncak di Q1. Tidak ada indikasi lonjakan besar.\n\n**Area Rawan**\n- Jawa Barat (19)\n- DKI Jakarta (9)\n- Lampung (7)\n- Banten (6)\n- Jawa Tengah (5)\n\n**Faktor Risiko**\n- Jalur transportasi utama (Tol Trans Jawa, Pelabuhan Merak) rawan penyelundupan.\n- Wilayah industri padat penduduk meningkatkan risiko perampokan.\n- Pengawasan longgar di pelabuhan kecil.",

            'jibom_antisipasi' => "**Rekomendasi Antisipasi JIBOM**\n\nTotal {total} kejadian.\n\n**Langkah Pencegahan**\n- Perketat pengawasan di pelabuhan dan jalur transportasi.\n- Tingkatkan patroli di area industri.\n- Kerjasama dengan Bea Cukai untuk deteksi dini.\n- Sosialisasi ke masyarakat tentang pelaporan.\n\n**Distribusi Geografis**\n- Jawa Barat (19)\n- DKI Jakarta (9)\n- Lampung (7)\n- Banten (6)\n- Jawa Tengah (5)",

            'kbrn_analisa' => "**Analisa Data KBRN (6 Bulan Terakhir)**\n\nTotal {total} kejadian.\n\n**Pola Tipe**\n- Dominasi kebocoran radiasi (52) dan limbah B3 (38) → 60% total.\n- Insiden transportasi bahan berbahaya (25).\n\n**Tren Bulanan**\n- Fluktuasi rendah, rata-rata 28/bulan. Tidak ada lonjakan signifikan.\n\n**Distribusi Geografis**\n- Kalimantan Timur (15)\n- Jawa Barat (12)\n- Papua (10)\n- Riau (8)\n- Sumatera Selatan (7)\n\n**Temuan Penting**\n- Konsentrasi di area tambang dan industri.\n- Kebocoran radiasi banyak dari fasilitas medis tua.\n- Limbah B3 dari industri kelapa sawit dominan di Sumatera.",

            'kbrn_prediksi' => "**Prediksi Data KBRN (6 Bulan Terakhir)**\n\nTotal {total} kejadian.\n\n**Prediksi**\nInsiden KBRN diperkirakan naik 10–15% di kuartal berikutnya seiring meningkatnya aktivitas industri. Area tambang dan fasilitas medis tua tetap jadi titik rawan utama.\n\n**Area Rawan**\n- Kalimantan Timur (15)\n- Jawa Barat (12)\n- Papua (10)\n- Riau (8)\n- Sumatera Selatan (7)\n\n**Faktor Risiko**\n- Fasilitas medis tua dengan peralatan radiologi usang.\n- Industri tambang tanpa pengelolaan limbah memadai.\n- Transportasi B3 tanpa pengawalan ketat.",

            'kbrn_antisipasi' => "**Rekomendasi Antisipasi KBRN**\n\nTotal {total} kejadian.\n\n**Langkah Pencegahan**\n- Audit fasilitas radiologi di rumah sakit.\n- Perketat izin pengelolaan limbah B3.\n- Standarisasi prosedur transportasi bahan berbahaya.\n- Pelatihan tanggap darurat KBRN untuk petugas lokal.\n- Inspeksi rutin fasilitas tambang.\n\n**Distribusi Geografis**\n- Kalimantan Timur (15)\n- Jawa Barat (12)\n- Papua (10)\n- Riau (8)\n- Sumatera Selatan (7)",

            'wan-teror_analisa' => "**Analisa Data WAN TEROR (6 Bulan Terakhir)**\n\nTotal {total} kejadian.\n\n**Pola Tipe**\n- 3 tipe dominan: jaringan-terorisme (40), ex-napiter (38), napiter (35) → 67.7% total.\n- Tipe lain (aksi-teror, bullying-perundungan, dll) 54 kejadian.\n- Keterkaitan napiter/ex-napiter tinggi (43.7%) → sinyal residivisme.\n\n**Tren Bulanan**\n- Feb: 15 (nadir) → Mar: 28 → Apr: 39 (puncak) → Mei: 29 → Jun: 26 → Jul: 30.\n- Fluktuasi, lonjakan April +30% dari rata-rata (27.8/bulan).\n\n**Distribusi Geografis**\n- Jawa Barat (19)\n- DKI Jakarta (9)\n- Lampung (7)\n- Papua Pegunungan (6)\n- Jawa Tengah (6)\n\n**Temuan Penting**\n- Dominasi ex-napiter & napiter → pengawasan/deradikalisasi perlu diperketat.\n- Lonjakan April perlu investigasi pemicu.\n- Papua Pegunungan tinggi (6) selaras dengan dinamika konflik setempat.",

            'wan-teror_prediksi' => "**Prediksi Data WAN TEROR (6 Bulan Terakhir)**\n\nTotal {total} kejadian.\n\n**Prediksi**\nKejadian serupa berlanjut. Rata-rata 28/bulan. Fluktuasi tinggi. Puncak seperti April (39) mungkin terulang. Tidak ada sinyal penurunan.\n\n**Area Rawan**\n- Jawa Barat (19)\n- DKI Jakarta (9)\n- Lampung (7)\n- Papua Pegunungan (6)\n- Jawa Tengah (6)\n\n**Faktor Risiko**\n- Jaringan terorisme dominan (40 kejadian). Sel aktif.\n- Ex-napiter (38) dan napiter (35) tinggi. Residivisme. Rekrutmen.\n- Konsentrasi di wilayah padat (Jawa Barat, DKI Jakarta) dan daerah konflik (Papua Pegunungan).\n- Pantauan longgar pada mantan napi teroris.",

            'wan-teror_antisipasi' => "**Rekomendasi Antisipasi WAN TEROR**\n\nTotal {total} kejadian.\n\n**Langkah Pencegahan**\n- Perkuat program deradikalisasi dan monitoring ex-napiter.\n- Tingkatkan patroli di area rawan: Jawa Barat, DKI Jakarta, Papua Pegunungan.\n- Kerjasama intelijen antar daerah untuk deteksi dini.\n- Libatkan tokoh masyarakat dalam pencegahan radikalisasi.\n- Perkuat cyber patrol untuk deteksi propaganda online.\n- Rehabilitasi dan reintegrasi mantan napiter ke masyarakat.\n\n**Distribusi Geografis**\n- Jawa Barat (19)\n- DKI Jakarta (9)\n- Lampung (7)\n- Papua Pegunungan (6)\n- Jawa Tengah (6)",
        ];
    }
}
