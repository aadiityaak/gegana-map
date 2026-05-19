<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Jibom\JibomIncidentController;
use App\Http\Controllers\WilayahController;

function crimeMapBaseUrl(string $endpoint): ?string
{
    $trimmed = rtrim($endpoint, '/');
    $base = preg_replace('~/api/monitoring-data$~', '', $trimmed);
    if (is_string($base) && $base !== '') {
        if ($base !== $trimmed) {
            return $base;
        }
    }

    $parts = parse_url($endpoint);
    $scheme = $parts['scheme'] ?? null;
    $host = $parts['host'] ?? null;
    if (!is_string($scheme) || !is_string($host)) {
        return null;
    }
    $port = isset($parts['port']) ? (':' . $parts['port']) : '';

    return $scheme . '://' . $host . $port;
}

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->get('/api/ipoleksosbudkam/monitoring-data', function (Request $request) {
    $endpoint = config('services.crime_map.data_endpoint');
    $token = config('services.crime_map.data_token');

    if (!is_string($endpoint) || trim($endpoint) === '' || !is_string($token) || trim($token) === '') {
        return response()->json(['message' => 'Server misconfigured: DATA_ENDPOINT / DATA_TOKEN not set.'], 500);
    }

    $base = crimeMapBaseUrl($endpoint);
    if (!is_string($base) || trim($base) === '') {
        return response()->json(['message' => 'Server misconfigured: invalid DATA_ENDPOINT.'], 500);
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

    $upstreamUrl = rtrim($base, '/') . '/api/monitoring-data';

    try {
        $upstream = Http::acceptJson()
            ->withToken($token)
            ->timeout(15)
            ->get($upstreamUrl, $query);
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

    $base = crimeMapBaseUrl($endpoint);
    if (!is_string($base) || trim($base) === '') {
        return response()->json(['message' => 'Server misconfigured: invalid DATA_ENDPOINT.'], 500);
    }

    if (!ctype_digit($id)) {
        return response()->json(['message' => 'Invalid id.'], 422);
    }

    $showEndpoint = rtrim($base, '/') . '/api/monitoring-data/' . $id;

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

    $base = crimeMapBaseUrl($endpoint);
    if (!is_string($base) || trim($base) === '') {
        return response()->json(['message' => 'Server misconfigured: invalid DATA_ENDPOINT.'], 500);
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

    $base = crimeMapBaseUrl($endpoint);
    if (!is_string($base) || trim($base) === '') {
        return response()->json(['message' => 'Server misconfigured: invalid DATA_ENDPOINT.'], 500);
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

    $base = crimeMapBaseUrl($endpoint);
    if (!is_string($base) || trim($base) === '') {
        return response()->json(['message' => 'Server misconfigured: invalid DATA_ENDPOINT.'], 500);
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

Route::middleware(['auth', 'verified'])->group(function () {
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

    Route::inertia('ipoleksosbudkam/detail/{id}', 'ipoleksosbudkam/Index', [
        'detailId' => fn(Request $request) => (int) $request->route('id'),
    ])->whereNumber('id')->name('ipoleksosbudkam.detail');

    Route::inertia('ipoleksosbudkam', 'ipoleksosbudkam/Index')->name('ipoleksosbudkam.index');
    Route::inertia('ipoleksosbudkam/{category}', 'ipoleksosbudkam/Index', [
        'category' => fn(Request $request) => $request->route('category'),
    ])->name('ipoleksosbudkam.category');
    Route::inertia('ipoleksosbudkam/{category}/{subcategory}', 'ipoleksosbudkam/Index', [
        'category' => fn(Request $request) => $request->route('category'),
        'subcategory' => fn(Request $request) => $request->route('subcategory'),
    ])->name('ipoleksosbudkam.subcategory');

    Route::prefix('api/wilayah')->group(function () {
        Route::get('provinces', [WilayahController::class, 'provinces'])->name('api.wilayah.provinces');
        Route::get('regencies', [WilayahController::class, 'regencies'])->name('api.wilayah.regencies');
        Route::get('districts', [WilayahController::class, 'districts'])->name('api.wilayah.districts');
        Route::get('villages', [WilayahController::class, 'villages'])->name('api.wilayah.villages');
    });

    Route::middleware(['role:superadmin,admin,adminvip'])->group(function () {
        Route::get('jibom', [JibomIncidentController::class, 'index'])->name('jibom.index');
        Route::get('jibom/create', [JibomIncidentController::class, 'create'])->name('jibom.create');
        Route::post('jibom', [JibomIncidentController::class, 'store'])->name('jibom.store');
        Route::get('jibom/{incident}/edit', [JibomIncidentController::class, 'edit'])->name('jibom.edit');
        Route::put('jibom/{incident}', [JibomIncidentController::class, 'update'])->name('jibom.update');
        Route::delete('jibom/{incident}', [JibomIncidentController::class, 'destroy'])->name('jibom.destroy');
    });
});

require __DIR__ . '/settings.php';
