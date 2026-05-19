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

Route::middleware(['auth', 'verified'])->get('/api/ketahanan-pangan/harga-peta', function (Request $request) {
    $endpoint = config('services.crime_map.data_endpoint');
    $token = config('services.crime_map.data_token');

    if (!is_string($endpoint) || trim($endpoint) === '' || !is_string($token) || trim($token) === '') {
        return response()->json(['message' => 'Server misconfigured: DATA_ENDPOINT / DATA_TOKEN not set.'], 500);
    }

    $trimmed = rtrim($endpoint, '/');
    $base = preg_replace('~/api/monitoring-data$~', '', $trimmed);
    if ($base === $trimmed) {
        $parts = parse_url($endpoint);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || !is_string($host)) {
            return response()->json(['message' => 'Server misconfigured: invalid DATA_ENDPOINT.'], 500);
        }
        $port = isset($parts['port']) ? (':' . $parts['port']) : '';
        $base = $scheme . '://' . $host . $port;
    }

    $upstreamUrl = rtrim($base, '/') . '/api/ketahanan-pangan/harga-peta-token';
    $query = array_filter($request->query(), fn($v) => $v !== null && $v !== '');

    try {
        $upstream = Http::withToken($token)->timeout(30)->get($upstreamUrl, $query);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Gagal menghubungi service crime-map.',
        ], 502);
    }

    $status = $upstream->status();
    if ($status < 100 || $status > 599) {
        $status = 502;
    }

    return response($upstream->body(), $status)
        ->header('Content-Type', $upstream->header('Content-Type', 'application/json'));
})->name('api.ketahanan-pangan.harga-peta');

Route::middleware(['auth', 'verified'])->get('/api/ketahanan-pangan/harga-informasi', function (Request $request) {
    $endpoint = config('services.crime_map.data_endpoint');
    $token = config('services.crime_map.data_token');

    if (!is_string($endpoint) || trim($endpoint) === '' || !is_string($token) || trim($token) === '') {
        return response()->json(['message' => 'Server misconfigured: DATA_ENDPOINT / DATA_TOKEN not set.'], 500);
    }

    $trimmed = rtrim($endpoint, '/');
    $base = preg_replace('~/api/monitoring-data$~', '', $trimmed);
    if ($base === $trimmed) {
        $parts = parse_url($endpoint);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || !is_string($host)) {
            return response()->json(['message' => 'Server misconfigured: invalid DATA_ENDPOINT.'], 500);
        }
        $port = isset($parts['port']) ? (':' . $parts['port']) : '';
        $base = $scheme . '://' . $host . $port;
    }

    $upstreamUrl = rtrim($base, '/') . '/api/ketahanan-pangan/harga-informasi-token';
    $query = array_filter($request->query(), fn($v) => $v !== null && $v !== '');

    try {
        $upstream = Http::withToken($token)->timeout(30)->get($upstreamUrl, $query);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Gagal menghubungi service crime-map.',
        ], 502);
    }

    $status = $upstream->status();
    if ($status < 100 || $status > 599) {
        $status = 502;
    }

    return response($upstream->body(), $status)
        ->header('Content-Type', $upstream->header('Content-Type', 'application/json'));
})->name('api.ketahanan-pangan.harga-informasi');

Route::middleware(['auth', 'verified'])->get('/api/ketahanan-pangan/indonesia-provinces-ts', function (Request $request) {
    $endpoint = config('services.crime_map.data_endpoint');
    $token = config('services.crime_map.data_token');

    if (!is_string($endpoint) || trim($endpoint) === '' || !is_string($token) || trim($token) === '') {
        return response()->json(['message' => 'Server misconfigured: DATA_ENDPOINT / DATA_TOKEN not set.'], 500);
    }

    $trimmed = rtrim($endpoint, '/');
    $base = preg_replace('~/api/monitoring-data$~', '', $trimmed);
    if ($base === $trimmed) {
        $parts = parse_url($endpoint);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || !is_string($host)) {
            return response()->json(['message' => 'Server misconfigured: invalid DATA_ENDPOINT.'], 500);
        }
        $port = isset($parts['port']) ? (':' . $parts['port']) : '';
        $base = $scheme . '://' . $host . $port;
    }

    $upstreamUrl = rtrim($base, '/') . '/api/ketahanan-pangan/indonesia-provinces-ts';

    try {
        $upstream = Http::withToken($token)->timeout(30)->get($upstreamUrl);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Gagal menghubungi service crime-map.',
        ], 502);
    }

    $status = $upstream->status();
    if ($status < 100 || $status > 599) {
        $status = 502;
    }

    return response($upstream->body(), $status)
        ->header('Content-Type', $upstream->header('Content-Type', 'text/plain; charset=UTF-8'));
})->name('api.ketahanan-pangan.indonesia-provinces-ts');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::inertia('ipoleksosbudkam/ekonomi/ekonomi-harga-sembako', 'KetahanPangan/Index', [
            'komoditas' => fn() => [
                ['value' => '27', 'label' => 'Beras Premium'],
                ['value' => '28', 'label' => 'Beras Medium'],
                ['value' => '109', 'label' => 'Beras SPHP'],
                ['value' => '102', 'label' => 'Jagung Tk Peternak'],
                ['value' => '29', 'label' => 'Kedelai Biji Kering (Impor)'],
                ['value' => '30', 'label' => 'Bawang Merah'],
                ['value' => '31', 'label' => 'Bawang Putih Bonggol'],
                ['value' => '32', 'label' => 'Cabai Merah Keriting'],
                ['value' => '126', 'label' => 'Cabai Merah Besar'],
                ['value' => '34', 'label' => 'Daging Sapi Murni'],
                ['value' => '33', 'label' => 'Cabai Rawit Merah'],
                ['value' => '35', 'label' => 'Daging Ayam Ras'],
                ['value' => '36', 'label' => 'Telur Ayam Ras'],
                ['value' => '37', 'label' => 'Gula Konsumsi'],
                ['value' => '38', 'label' => 'Minyak Goreng Kemasan'],
                ['value' => '101', 'label' => 'Minyak Goreng Curah'],
                ['value' => '40', 'label' => 'Tepung Terigu (Curah)'],
                ['value' => '127', 'label' => 'Minyakita'],
                ['value' => '108', 'label' => 'Tepung Terigu Kemasan'],
                ['value' => '104', 'label' => 'Ikan Kembung'],
                ['value' => '105', 'label' => 'Ikan Tongkol'],
                ['value' => '106', 'label' => 'Ikan Bandeng'],
                ['value' => '107', 'label' => 'Garam Konsumsi'],
                ['value' => '149', 'label' => 'Daging Kerbau Beku (Impor Luar Negeri)'],
                ['value' => '152', 'label' => 'Daging Kerbau Segar (Lokal)'],
            ],
        ])->name('ipoleksosbudkam.harga-sembako');

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
