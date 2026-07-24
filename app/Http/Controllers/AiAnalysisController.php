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
        $startDate = now()->subMonths($months);

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
            ->limit(500)
            ->get()
            ->map(function ($row) {
                $row->photos = null;
                return $row;
            })
            ->values()
            ->toArray();

        $totalCount = count($data);
        $prompt = $this->buildPrompt($action, $config['label'], $totalCount, $data, $period);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($settings['endpoint'], [
                'model' => $settings['model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Anda adalah asisten analis keamanan. Berikan analisis dalam bahasa Indonesia yang terstruktur dan ringkas.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 2000,
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

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? ($body['response'] ?? 'No response from AI.');

            // Simpan riwayat
            $history = AiAnalysisHistory::create([
                'module' => $module,
                'action' => $action,
                'period' => $period,
                'total_data' => $totalCount,
                'prompt' => $prompt,
                'result' => $content,
            ]);

            return response()->json([
                'id' => $history->id,
                'action' => $action,
                'module' => $module,
                'period' => $period,
                'total_data' => $totalCount,
                'result' => $content,
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

    public function history(Request $request, string $module): JsonResponse
    {
        if (!isset(self::MODULES[$module])) {
            return response()->json(['message' => 'Module not found.'], 404);
        }

        $rows = AiAnalysisHistory::where('module', $module)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'module', 'action', 'period', 'total_data', 'result', 'created_at']);

        return response()->json(['data' => $rows]);
    }

    private function buildPrompt(string $action, string $label, int $totalCount, array $data, string $period): string
    {
        $periodLabel = match ($period) {
            '1month' => '1 bulan terakhir',
            '6months' => '6 bulan terakhir',
            '1year' => '1 tahun terakhir',
        };

        $summary = "Data {$label} ({$periodLabel}): total {$totalCount} kejadian.\n\n";
        if ($totalCount > 0) {
            $summary .= "Contoh data:\n";
            foreach (array_slice($data, 0, 20) as $row) {
                $summary .= "- ID:{$row->id} type:{$row->incident_type} lokasi:{$row->province_name} tgl:{$row->created_at}\n";
            }
        }

        $instruction = match ($action) {
            'analisa' => "Lakukan analisis terhadap data {$label} {$periodLabel}. Berikan insight tentang pola, tren, distribusi geografis, dan temuan penting.",
            'prediksi' => "Berdasarkan data {$label} {$periodLabel}, berikan prediksi tentang kemungkinan kejadian serupa di masa mendatang. Sertakan area rawan dan faktor risiko.",
            'antisipasi' => "Berdasarkan data {$label} {$periodLabel}, berikan rekomendasi antisipasi dan langkah-langkah pencegahan yang dapat diambil.",
            default => "Analisis data {$label}.",
        };

        return "{$instruction}\n\n{$summary}";
    }
}
