<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseDevelopment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HermesCaseDevelopmentController extends Controller
{
    private const INCIDENT_TYPES = ['jibom', 'kbrn', 'wan_teror'];

    public function store(Request $request, string $incidentId)
    {
        $validated = $request->validate([
            'incident_type' => ['required', 'string', Rule::in(self::INCIDENT_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'source_url' => ['nullable', 'string', 'max:2048', 'url'],
            'reported_at' => ['nullable', 'date'],
        ]);

        if (!ctype_digit($incidentId)) {
            return response()->json(['message' => 'Invalid incident_id.'], 422);
        }

        $dev = CaseDevelopment::create([
            'incident_type' => $validated['incident_type'],
            'incident_id' => (int) $incidentId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'source_url' => $validated['source_url'] ?? null,
            'reported_at' => $validated['reported_at'] ?? now(),
        ]);

        return response()->json([
            'status' => 'created',
            'development_id' => $dev->id,
        ], 201);
    }
}
