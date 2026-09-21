<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysisHistory;
use App\Support\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAnalysisController extends Controller
{
    private const MODULES = [
        'jibom' => ['table' => 'jibom_incidents', 'label' => 'JIBOM'],
        'kbrn' => ['table' => 'kbrn_incidents', 'label' => 'KBRN'],
        'wan-teror' => ['table' => 'wan_teror_incidents', 'label' => 'WAN TEROR'],
    ];

    private const PERIOD_MONTHS = [
        '1month' => 1,
        '6months' => 6,
        '1year' => 12,
    ];

    public function analyze(Request $request, string $module, AiSettings $aiSettings): JsonResponse
    {
        if (!isset(self::MODULES[$module])) {
            return response()->json(['message' => 'Module not found.'], 404);
        }

        $action = $request->query('action', 'analisa');
        if (!in_array($action, ['analisa', 'prediksi', 'antisipasi'], true)) {
            return response()->json(['message' => 'Action must be analisa, prediksi, or antisipasi.'], 422);
        }

        $period = $request->query('period', '1month');
        if (!isset(self::PERIOD_MONTHS[$period])) {
            return response()->json(['message' => 'Period must be 1month, 6months, or 1year.'], 422);
        }

        $settings = $aiSettings->shared();
        if (empty($settings['endpoint']) || empty($settings['api_key']) || empty($settings['model'])) {
            return response()->json(['message' => 'AI not configured. Go to Settings > AI first.'], 400);
        }

        $config = self::MODULES[$module];
        $months = self::PERIOD_MONTHS[$period];
        $startDate = now()->startOfMonth()->subMonthsNoOverflow($months - 1);

        $table = $config['table'];
        $data = DB::table("{$table} as t")
            ->leftJoin('reg_provinces as p', 'p.id', '=', 't.province_id')
            ->where('t.created_at', '>=', $startDate)
            ->select([
                't.id',
                't.incident_type',
                't.province_id',
                'p.name as province_name',
                't.created_at',
            ])
            ->orderByDesc('t.created_at')
            ->limit(300)
            ->get()
            ->map(function ($row) {
                $row->photos = null;
                return $row;
            })
            ->values()
            ->toArray();

        $stats = $this->buildStats($table, $months);
        $totalCount = $stats['kpi']['total'];
        $prompt = $this->buildPrompt($action, $config['label'], $totalCount, $data, $period);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($settings['endpoint'], [
                'model' => $settings['model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Anda adalah asisten analis keamanan. Jawab langsung dengan hasil analisis — jangan menuliskan proses berpikir. Gunakan bahasa Indonesia yang terstruktur dan ringkas.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 8000,
                'temperature' => 0.3,
            ]);

            if (!$response->successful()) {
                $status = $response->status();
                $body = $response->body();
                $reason = 'AI API returned HTTP ' . $status;
                if ($body !== '' && $body !== null) {
                    $reason .= ': ' . substr($body, 0, 2000);
                } else {
                    $reason .= ' (empty response body)';
                }
                Log::error('AI API call failed', [
                    'status' => $status,
                    'body' => $body,
                    'endpoint' => $settings['endpoint'],
                ]);
                return response()->json(['message' => $reason], 502);
            }

            $body = $this->extractJsonBody($response);
            $content = $body['choices'][0]['message']['content'] ?? ($body['response'] ?? '');
            $finish = $body['choices'][0]['finish_reason'] ?? null;

            if (trim((string) $content) === '') {
                // Model reasoning (mis. deepseek-*-flash) bisa menghabiskan seluruh
                // max_tokens untuk penalaran internal -> content kosong.
                $panjangPenalaran = strlen((string) ($body['choices'][0]['message']['reasoning_content'] ?? ''));
                Log::warning('Jawaban AI kosong', [
                    'module' => $module,
                    'finish_reason' => $finish,
                    'reasoning_chars' => $panjangPenalaran,
                ]);

                return response()->json([
                    'message' => 'Model AI tidak mengembalikan jawaban (token habis untuk penalaran internal). Coba jalankan lagi, atau pilih model non-reasoning di Pengaturan AI.',
                    'finish_reason' => $finish,
                    'penalaran_karakter' => $panjangPenalaran,
                ], 502);
            }

            // Simpan riwayat
            $history = AiAnalysisHistory::create([
                'module' => $module,
                'action' => $action,
                'period' => $period,
                'total_data' => $totalCount,
                'prompt' => $prompt,
                'result' => $content,
                'stats' => $stats,
            ]);

            return response()->json([
                'id' => $history->id,
                'action' => $action,
                'module' => $module,
                'period' => $period,
                'total_data' => $totalCount,
                'result' => $content,
                'stats' => $stats,
                'created_at' => $history->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AI analysis failed', [
                'error' => $e->getMessage(),
                'module' => $module,
                'action' => $action,
            ]);
            return response()->json([
                'message' => 'Failed to call AI: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function testConnection(Request $request, AiSettings $aiSettings): JsonResponse
    {
        $settings = $aiSettings->shared();
        if (empty($settings['endpoint']) || empty($settings['api_key']) || empty($settings['model'])) {
            return response()->json(['message' => 'AI not configured. Go to Settings > AI first.'], 400);
        }

        $prompt = 'Ucapkan "pong" dan jelaskan singkat apa kegunaanmu.';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($settings['endpoint'], [
                'model' => $settings['model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Anda adalah asisten analis keamanan.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 100,
                'temperature' => 0.3,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'HTTP ' . $response->status(),
                    'body' => $response->body(),
                ], 502);
            }

            $body = $this->extractJsonBody($response);
            $content = $body['choices'][0]['message']['content'] ?? ($body['response'] ?? 'No response from AI.');

            return response()->json([
                'status' => 'ok',
                'model' => $settings['model'],
                'response' => $content,
                'raw' => $body,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('AI test failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to call AI: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function history(Request $request, string $module): JsonResponse
    {
        if (!isset(self::MODULES[$module])) {
            return response()->json(['message' => 'Module not found.'], 404);
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $page = (int) $request->query('page', 1);

        $paginator = AiAnalysisHistory::where('module', $module)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['id', 'module', 'action', 'period', 'total_data', 'result', 'stats', 'created_at'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function allHistory(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 100);
        $page = (int) $request->query('page', 1);

        $query = AiAnalysisHistory::orderByDesc('created_at');

        if ($request->query('view') === 'ai') {
            $query->where('action', 'analisa');
        }

        $paginator = $query->paginate($perPage, ['id', 'module', 'action', 'period', 'total_data', 'result', 'stats', 'created_at'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $history = AiAnalysisHistory::find($id);
        if (!$history) {
            return response()->json(['message' => 'Riwayat tidak ditemukan.'], 404);
        }

        $history->delete();

        return response()->json(['message' => 'Riwayat berhasil dihapus.']);
    }

    private function extractJsonBody($response): array
    {
        $body = $response->json();
        if (!is_array($body)) {
            // Try parsing the body, handling SSE-style trailing "data: [DONE]"
            $raw = trim($response->body());
            // Extract the first JSON object from the response
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                // Handle SSE format: JSON followed by "data: [DONE]"
                if (preg_match('/^{.+}/s', $raw, $matches)) {
                    $decoded = json_decode($matches[0], true);
                }
            }
            $body = is_array($decoded) ? $decoded : [];
        }
        return $body;
    }

    private function buildPrompt(string $action, string $label, int $totalCount, array $data, string $period): string
    {
        $periodLabel = match ($period) {
            '1month' => '1 bulan terakhir',
            '6months' => '6 bulan terakhir',
            '1year' => '1 tahun terakhir',
        };

        $summary = $this->buildSummary($data, $label, $periodLabel, $totalCount);

        $instruction = match ($action) {
            'analisa' => "Lakukan analisis terhadap data {$label} {$periodLabel}. Berikan insight tentang pola, tren, distribusi geografis, dan temuan penting.",
            'prediksi' => "Berdasarkan data {$label} {$periodLabel}, berikan prediksi tentang kemungkinan kejadian serupa di masa mendatang. Sertakan area rawan dan faktor risiko.",
            'antisipasi' => "Berdasarkan data {$label} {$periodLabel}, berikan rekomendasi antisipasi dan langkah-langkah pencegahan yang dapat diambil.",
            default => "Analisis data {$label}.",
        };

        return "{$instruction}\n\n{$summary}";
    }

    private function buildSummary(array $data, string $label, string $periodLabel, ?int $totalSebenarnya = null): string
    {
        $total = count($data);

        if ($total === 0) {
            return "Data {$label} ({$periodLabel}): tidak ada kejadian.\n";
        }

        $infoTotal = ($totalSebenarnya !== null && $totalSebenarnya > $total)
            ? "{$totalSebenarnya} kejadian (rincian di bawah = {$total} data terbaru)"
            : (string) $total;

        $summary = "Data {$label} ({$periodLabel}):\n";
        $summary .= "Total kejadian: {$infoTotal}\n\n";

        // Provinsi terbanyak (top 5)
        $provinceCount = collect($data)
            ->groupBy('province_name')
            ->map->count()
            ->sortDesc()
            ->take(5);
        $summary .= "Provinsi terbanyak:\n";
        foreach ($provinceCount as $name => $count) {
            $summary .= "- {$name}: {$count}\n";
        }

        // Tipe kejadian terbanyak (top 3)
        $typeCount = collect($data)
            ->groupBy('incident_type')
            ->map->count()
            ->sortDesc()
            ->take(3);
        $summary .= "\nTipe kejadian terbanyak:\n";
        foreach ($typeCount as $type => $count) {
            $summary .= "- {$type}: {$count}\n";
        }

        // Tren per bulan
        $monthly = collect($data)
            ->map(function ($row) {
                return \Carbon\Carbon::parse($row->created_at)->format('M Y');
            })
            ->groupBy(fn($m) => $m)
            ->map->count()
            ->sortKeys();
        $summary .= "\nTren bulanan:\n";
        foreach ($monthly as $month => $count) {
            $summary .= "- {$month}: {$count}\n";
        }

        // Sampling acak representatif (30 data)
        $summary .= "\nContoh data acak:\n";
        $sample = collect($data)->shuffle()->take(min(30, $total))->all();
        foreach ($sample as $row) {
            $summary .= "- ID:{$row->id} type:{$row->incident_type} lokasi:{$row->province_name} tgl:{$row->created_at}\n";
        }

        return $summary;
    }

    /**
     * Statistik ringkas untuk visualisasi grafik.
     * Dihitung server (bukan hasil parsing teks AI) supaya angka selalu akurat
     * dan grafik bisa digambar ulang kapan saja — termasuk dari riwayat analisa.
     */
    private function buildStats(string $table, int $months): array
    {
        // Jendela statistik memakai bulan kalender penuh (mis. 6 bulan terakhir),
        // supaya jumlah titik pada grafik tren sama dengan angka pada kartu ringkasan.
        $awal = now()->startOfMonth()->subMonthsNoOverflow($months - 1);

        $rows = DB::table("{$table} as t")
            ->leftJoin('reg_provinces as p', 'p.id', '=', 't.province_id')
            ->where('t.created_at', '>=', $awal)
            ->select(['t.incident_type', 't.province_id', 'p.name as province_name', 't.created_at'])
            ->orderBy('t.created_at')
            ->limit(5000)
            ->get();

        $namaBulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];

        // Tren per bulan — semua bulan dalam periode tetap muncul (0 kalau tidak ada kejadian).
        $bulan = [];
        $posisiBulan = [];
        $kursor = $awal->copy();
        $batas = now()->startOfMonth();

        while ($kursor->lessThanOrEqualTo($batas)) {
            $posisiBulan[$kursor->format('Y-m')] = count($bulan);
            $bulan[] = [
                'label' => $namaBulan[(int) $kursor->month] . ' ' . $kursor->format('y'),
                'value' => 0,
            ];
            $kursor = $kursor->addMonthNoOverflow();
        }

        $hitungProvinsi = [];
        $hitungTipe = [];
        $provinsiUnik = [];

        foreach ($rows as $row) {
            $key = \Carbon\Carbon::parse($row->created_at)->format('Y-m');
            if (isset($posisiBulan[$key])) {
                $bulan[$posisiBulan[$key]]['value']++;
            }

            $namaProvinsi = trim((string) ($row->province_name ?? '')) !== '' ? (string) $row->province_name : 'Belum diketahui';
            $hitungProvinsi[$namaProvinsi] = ($hitungProvinsi[$namaProvinsi] ?? 0) + 1;

            if (! empty($row->province_id)) {
                $provinsiUnik[(string) $row->province_id] = true;
            }

            $namaTipe = $this->labelTipe((string) ($row->incident_type ?? ''));
            $hitungTipe[$namaTipe] = ($hitungTipe[$namaTipe] ?? 0) + 1;
        }

        arsort($hitungProvinsi);
        arsort($hitungTipe);

        $total = $rows->count();
        $totalSebelumnya = DB::table($table)
            ->where('created_at', '>=', $awal->copy()->subMonthsNoOverflow($months))
            ->where('created_at', '<', $awal)
            ->count();

        $delta = $totalSebelumnya > 0
            ? round((($total - $totalSebelumnya) / $totalSebelumnya) * 100, 1)
            : ($total > 0 ? 100.0 : 0.0);

        $puncak = ['label' => '-', 'value' => 0];
        foreach ($bulan as $b) {
            if ($b['value'] > $puncak['value']) {
                $puncak = ['label' => $b['label'], 'value' => $b['value']];
            }
        }

        return [
            'kpi' => [
                'total' => $total,
                'provinsi' => count($provinsiUnik),
                'rata_per_bulan' => $months > 0 ? round($total / $months, 1) : 0.0,
                'sebelumnya' => $totalSebelumnya,
                'delta_persen' => $delta,
                'puncak_label' => $puncak['label'],
                'puncak_value' => $puncak['value'],
            ],
            'tren' => $bulan,
            'provinsi' => $this->potong($hitungProvinsi, 8),
            'tipe' => $this->potong($hitungTipe, 6),
        ];
    }

    /** Ubah hasil hitungan (nama => jumlah) jadi daftar label/value terurut, maksimal $maks item. */
    private function potong(array $hitungan, int $maks): array
    {
        $hasil = [];

        foreach (array_slice($hitungan, 0, $maks, true) as $nama => $jumlah) {
            $hasil[] = ['label' => (string) $nama, 'value' => (int) $jumlah];
        }

        return $hasil;
    }

    private function labelTipe(string $tipe): string
    {
        $tipe = trim($tipe);

        if ($tipe === '') {
            return 'Belum diketahui';
        }

        return ucwords(str_replace(['-', '_'], ' ', $tipe));
    }
}
