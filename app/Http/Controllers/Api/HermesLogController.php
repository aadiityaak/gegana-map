<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HermesAgentLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HermesLogController extends Controller
{
    private const VALID_TYPES = [
        'search_start',
        'search_done',
        'summarizing',
        'summary_done',
        'insert',
        'update',
        'skip',
        'error',
        'info',
        'scan_start',
        'scan_done',
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(self::VALID_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $log = HermesAgentLog::create([
            'type' => $validated['type'],
            'title' => $validated['title'],
            'message' => $validated['message'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'status' => 'created',
            'log_id' => $log->id,
        ], 201);
    }

    public function latest(Request $request)
    {
        $since = $request->query('since');
        $query = HermesAgentLog::orderByDesc('id');

        if (is_string($since) && ctype_digit($since)) {
            $query->where('id', '>', (int) $since);
        }

        $logs = $query->limit(100)->get();

        return response()->json([
            'logs' => $logs,
            'latest_id' => $logs->first()?->id ?? (int) ($since ?? 0),
        ]);
    }
}
