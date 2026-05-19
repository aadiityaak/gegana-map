<?php

use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        $team = $user?->currentTeam ?? $user?->personalTeam();

        if (! $team) {
            abort(403);
        }

        return redirect()->route('dashboard', ['current_team' => $team->slug]);
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->get('/api/ipoleksosbudkam/monitoring-data', function (Request $request) {
    $endpoint = config('services.crime_map.data_endpoint');
    $token = config('services.crime_map.data_token');

    if (!is_string($endpoint) || trim($endpoint) === '' || !is_string($token) || trim($token) === '') {
        return response()->json(['message' => 'Server misconfigured: DATA_ENDPOINT / DATA_TOKEN not set.'], 500);
    }

    $allowedQueryKeys = [
        'page',
        'per_page',
        'sort_by',
        'sort_dir',
        'search',
        'status',
        'level',
        'start_date',
        'end_date',
        'category',
        'subcategory',
    ];

    $query = array_filter(
        $request->only($allowedQueryKeys),
        fn($value) => $value !== null && $value !== '',
    );

    try {
        $upstream = Http::acceptJson()
            ->withToken($token)
            ->timeout(15)
            ->get($endpoint, $query);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Gagal menghubungi service crime-map. Pastikan crime-map (port 8000) sedang berjalan dan DATA_ENDPOINT benar.',
        ], 502);
    }

    $status = $upstream->status();
    if ($status < 100 || $status > 599) {
        $status = 502;
    }

    return response($upstream->body(), $status)
        ->header('Content-Type', $upstream->header('Content-Type', 'application/json'));
})->name('api.ipoleksosbudkam.monitoring-data');

Route::middleware(['auth', 'verified'])->get('/api/ipoleksosbudkam/monitoring-data/{id}', function (Request $request, string $id) {
    $endpoint = config('services.crime_map.data_endpoint');
    $token = config('services.crime_map.data_token');

    if (!is_string($endpoint) || trim($endpoint) === '' || !is_string($token) || trim($token) === '') {
        return response()->json(['message' => 'Server misconfigured: DATA_ENDPOINT / DATA_TOKEN not set.'], 500);
    }

    if (!ctype_digit($id)) {
        return response()->json(['message' => 'Invalid id.'], 422);
    }

    $showEndpoint = rtrim($endpoint, '/') . '/' . $id;

    try {
        $upstream = Http::acceptJson()
            ->withToken($token)
            ->timeout(15)
            ->get($showEndpoint);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Gagal menghubungi service crime-map. Pastikan crime-map (port 8000) sedang berjalan dan DATA_ENDPOINT benar.',
        ], 502);
    }

    $status = $upstream->status();
    if ($status < 100 || $status > 599) {
        $status = 502;
    }

    return response($upstream->body(), $status)
        ->header('Content-Type', $upstream->header('Content-Type', 'application/json'));
})->whereNumber('id')->name('api.ipoleksosbudkam.monitoring-data.show');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::inertia('ipoleksosbudkam', 'ipoleksosbudkam/Index')->name('ipoleksosbudkam.index');
        Route::inertia('ipoleksosbudkam/{category}', 'ipoleksosbudkam/Index', [
            'category' => fn(Request $request) => $request->route('category'),
        ])->name('ipoleksosbudkam.category');
        Route::inertia('ipoleksosbudkam/{category}/{subcategory}', 'ipoleksosbudkam/Index', [
            'category' => fn(Request $request) => $request->route('category'),
            'subcategory' => fn(Request $request) => $request->route('subcategory'),
        ])->name('ipoleksosbudkam.subcategory');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__ . '/settings.php';
