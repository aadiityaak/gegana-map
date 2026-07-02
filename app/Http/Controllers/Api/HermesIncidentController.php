<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseDevelopment;
use App\Models\JibomIncident;
use App\Models\KBRNIncident;
use App\Models\WanTerorIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HermesIncidentController extends Controller
{
    private const INCIDENT_TYPES = ['jibom', 'kbrn', 'wan_teror'];

    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'incident_type' => ['required', 'string', Rule::in(self::INCIDENT_TYPES)],
            'news_url' => ['nullable', 'string', 'max:2048', 'url'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'province_id' => ['nullable', 'string', 'size:2'],
            'regency_id' => ['nullable', 'string', 'size:4'],
            'district_id' => ['nullable', 'string', 'size:6'],
            'village_id' => ['nullable', 'string', 'size:10'],
            'incident_category' => ['nullable', 'string', 'max:255'],
            'finding_type' => ['nullable', 'string', 'max:255'],
            'reported_at' => ['nullable', 'date'],
        ]);

        $type = $validated['incident_type'];
        $newsUrl = $validated['news_url'] ?? null;

        $existing = null;
        if (is_string($newsUrl) && $newsUrl !== '') {
            $existing = $this->findByNewsUrl($type, $newsUrl);
        }

        if ($existing) {
            // Cek apakah ada info perkembangan baru (title/description berbeda dari berita awal)
            $title = $validated['title'] ?? null;
            $desc = $validated['description'] ?? null;

            if ($title || $desc) {
                $dev = CaseDevelopment::create([
                    'incident_type' => $type,
                    'incident_id' => $existing->id,
                    'title' => $title ?? 'Update',
                    'description' => $desc,
                    'source_url' => $newsUrl,
                    'reported_at' => $validated['reported_at'] ?? now(),
                ]);

                return response()->json([
                    'status' => 'updated',
                    'incident_id' => $existing->id,
                    'development_id' => $dev->id,
                    'message' => 'Perkembangan ditambahkan ke insiden yang sudah ada.',
                ]);
            }

            return response()->json([
                'status' => 'skipped',
                'incident_id' => $existing->id,
                'message' => 'Artikel sudah ada. Tidak ada perkembangan baru.',
            ]);
        }

        // Buat insiden baru
        $model = $this->modelForType($type);
        $incident = $model::create([
            'incident_type' => $validated['incident_category'] ?? 'ancaman',
            'finding_type' => $validated['finding_type'] ?? null,
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'news_source' => 'ai_agent',
            'news_url' => $newsUrl,
            'province_id' => $validated['province_id'] ?? null,
            'regency_id' => $validated['regency_id'] ?? null,
            'district_id' => $validated['district_id'] ?? null,
            'village_id' => $validated['village_id'] ?? null,
        ]);

        return response()->json([
            'status' => 'created',
            'incident_id' => $incident->id,
            'message' => 'Insiden baru dibuat.',
        ], 201);
    }

    public function check(Request $request)
    {
        $request->validate([
            'news_url' => ['required', 'string', 'max:2048', 'url'],
            'incident_type' => ['required', 'string', Rule::in(self::INCIDENT_TYPES)],
        ]);

        $existing = $this->findByNewsUrl(
            $request->input('incident_type'),
            $request->input('news_url'),
        );

        if (!$existing) {
            return response()->json(['exists' => false]);
        }

        $devCount = CaseDevelopment::where('incident_type', $request->input('incident_type'))
            ->where('incident_id', $existing->id)
            ->count();

        return response()->json([
            'exists' => true,
            'incident_id' => $existing->id,
            'incident_type' => $request->input('incident_type'),
            'development_count' => $devCount,
        ]);
    }

    private function findByNewsUrl(string $type, string $url)
    {
        $model = $this->modelForType($type);
        return $model::where('news_url', $url)->first();
    }

    private function modelForType(string $type)
    {
        return match ($type) {
            'jibom' => JibomIncident::class,
            'kbrn' => KBRNIncident::class,
            'wan_teror' => WanTerorIncident::class,
        };
    }
}
