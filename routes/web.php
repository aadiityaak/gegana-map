<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Jibom\JibomIncidentController;
use App\Http\Controllers\Kwrn\KwrnIncidentController;
use App\Http\Controllers\WanTeror\WanTerorIncidentController;
use App\Http\Controllers\WilayahController;

if (! function_exists('crimeMapBaseUrl')) {
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
}

if (! function_exists('resolveIndonesiaMapSvgPath')) {
    function resolveIndonesiaMapSvgPath(?string $envPath): ?string
    {
        $candidates = [];

        if (is_string($envPath) && trim($envPath) !== '') {
            $candidates[] = $envPath;
            $candidates[] = base_path($envPath);
        }

        $candidates[] = public_path('maps/indonesiaHigh.svg');

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
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
    Route::inertia('dashboard', 'Dashboard', [
        'dashboard' => function () {
            $schema = DB::getSchemaBuilder();

            $tables = [
                'jibom' => 'jibom_incidents',
                'kwrn' => 'kwrn_incidents',
                'wan_teror' => 'wan_teror_incidents',
            ];

            $hasTable = fn(string $name) => $schema->hasTable($name);

            $safeCount = function (string $table) use ($hasTable): int {
                if (! $hasTable($table)) return 0;
                return (int) DB::table($table)->count();
            };

            $safeMaxCreatedAt = function (string $table) use ($hasTable): ?string {
                if (! $hasTable($table)) return null;
                $value = DB::table($table)->max('created_at');
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('c');
                }
                if (is_string($value) && trim($value) !== '') {
                    try {
                        return (new \DateTimeImmutable($value))->format('c');
                    } catch (\Throwable $e) {
                        return null;
                    }
                }
                return null;
            };

            $safeCountsByType = function (string $table, array $types) use ($hasTable): array {
                $out = [];
                foreach ($types as $t) {
                    $out[$t] = 0;
                }
                if (! $hasTable($table)) return $out;

                $rows = DB::table($table)
                    ->select(['incident_type', DB::raw('count(*) as count')])
                    ->whereIn('incident_type', $types)
                    ->groupBy('incident_type')
                    ->get();

                foreach ($rows as $row) {
                    $k = is_string($row->incident_type ?? null) ? $row->incident_type : null;
                    if (! $k || ! array_key_exists($k, $out)) continue;
                    $out[$k] = (int) ($row->count ?? 0);
                }

                return $out;
            };

            $safeTopProvinces = function (string $table, ?array $types, int $limit = 8) use ($hasTable): array {
                if (! $hasTable($table)) return [];

                $q = DB::table($table . ' as t')
                    ->join('reg_provinces as p', 'p.id', '=', 't.province_id')
                    ->select([
                        'p.id',
                        'p.name',
                        DB::raw('count(*) as count'),
                    ])
                    ->groupBy('p.id', 'p.name')
                    ->orderByDesc('count')
                    ->limit($limit);

                if (is_array($types) && count($types) > 0) {
                    $q->whereIn('t.incident_type', $types);
                }

                $rows = $q->get();

                return $rows
                    ->map(fn($r) => [
                        'id' => (string) ($r->id ?? ''),
                        'name' => (string) ($r->name ?? ''),
                        'count' => (int) ($r->count ?? 0),
                    ])
                    ->values()
                    ->all();
            };

            $jibomTypes = ['ancaman', 'temuan', 'ledakan'];
            $kwrnTypes = ['ancaman', 'temuan', 'ledakan'];
            $wanTerorTypes = [
                'napiter',
                'ex-napiter',
                'jaringan-terorisme',
                'bullying-perundungan',
                'aksi-teror',
            ];

            $totals = [
                'jibom' => $safeCount($tables['jibom']),
                'kwrn' => $safeCount($tables['kwrn']),
                'wanTeror' => $safeCount($tables['wan_teror']),
            ];
            $totals['all'] = array_sum($totals);

            $modules = [
                [
                    'key' => 'jibom',
                    'title' => 'JIBOM',
                    'total' => $totals['jibom'],
                    'types' => [
                        ['value' => 'ancaman', 'label' => 'Ancaman Pengeboman'],
                        ['value' => 'temuan', 'label' => 'Temuan Bom'],
                        ['value' => 'ledakan', 'label' => 'Ledakan Bom'],
                    ],
                    'countsByType' => $safeCountsByType($tables['jibom'], $jibomTypes),
                    'topProvinces' => $safeTopProvinces($tables['jibom'], $jibomTypes),
                    'lastCreatedAt' => $safeMaxCreatedAt($tables['jibom']),
                    'href' => '/jibom',
                ],
                [
                    'key' => 'kwrn',
                    'title' => 'KWRN',
                    'total' => $totals['kwrn'],
                    'types' => [
                        ['value' => 'ancaman', 'label' => 'Ancaman KWRN'],
                        ['value' => 'temuan', 'label' => 'Temuan KWRN'],
                        ['value' => 'ledakan', 'label' => 'Ledakan KWRN'],
                    ],
                    'countsByType' => $safeCountsByType($tables['kwrn'], $kwrnTypes),
                    'topProvinces' => $safeTopProvinces($tables['kwrn'], $kwrnTypes),
                    'lastCreatedAt' => $safeMaxCreatedAt($tables['kwrn']),
                    'href' => '/kwrn',
                ],
                [
                    'key' => 'wanTeror',
                    'title' => 'WAN TEROR',
                    'total' => $totals['wanTeror'],
                    'types' => [
                        ['value' => 'napiter', 'label' => 'Data Napiter'],
                        ['value' => 'ex-napiter', 'label' => 'Data EX Napiter'],
                        ['value' => 'jaringan-terorisme', 'label' => 'Jaringan Terorisme'],
                        ['value' => 'bullying-perundungan', 'label' => 'Bullying/Perundungan'],
                        ['value' => 'aksi-teror', 'label' => 'Aksi Teror'],
                    ],
                    'countsByType' => $safeCountsByType($tables['wan_teror'], $wanTerorTypes),
                    'topProvinces' => $safeTopProvinces($tables['wan_teror'], $wanTerorTypes),
                    'lastCreatedAt' => $safeMaxCreatedAt($tables['wan_teror']),
                    'href' => '/wan-teror',
                ],
            ];

            $topAll = [];
            foreach ($tables as $table) {
                if (! $hasTable($table)) continue;
                $rows = DB::table($table . ' as t')
                    ->join('reg_provinces as p', 'p.id', '=', 't.province_id')
                    ->select(['p.id', 'p.name', DB::raw('count(*) as count')])
                    ->groupBy('p.id', 'p.name')
                    ->get();
                foreach ($rows as $r) {
                    $id = (string) ($r->id ?? '');
                    if ($id === '') continue;
                    if (! isset($topAll[$id])) {
                        $topAll[$id] = [
                            'id' => $id,
                            'name' => (string) ($r->name ?? ''),
                            'count' => 0,
                        ];
                    }
                    $topAll[$id]['count'] += (int) ($r->count ?? 0);
                }
            }
            $topProvincesAll = array_values($topAll);
            usort($topProvincesAll, fn($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
            $topProvincesAll = array_slice($topProvincesAll, 0, 10);

            $driver = DB::connection()->getDriverName();
            $monthExpr = match ($driver) {
                'sqlite' => "strftime('%Y-%m', created_at)",
                'pgsql' => "to_char(created_at, 'YYYY-MM')",
                default => "DATE_FORMAT(created_at, '%Y-%m')",
            };

            $startMonth = now()->startOfMonth()->subMonths(11);
            $endMonth = now()->startOfMonth()->addMonth();
            $monthKeys = [];
            for ($i = 0; $i < 12; $i++) {
                $monthKeys[] = $startMonth->copy()->addMonths($i)->format('Y-m');
            }

            $safeMonthlyCounts = function (string $table) use ($hasTable, $monthExpr, $startMonth, $endMonth): array {
                if (! $hasTable($table)) return [];

                $rows = DB::table($table)
                    ->select([DB::raw($monthExpr . ' as ym'), DB::raw('count(*) as count')])
                    ->where('created_at', '>=', $startMonth)
                    ->where('created_at', '<', $endMonth)
                    ->groupBy('ym')
                    ->get();

                $map = [];
                foreach ($rows as $row) {
                    $k = is_string($row->ym ?? null) ? $row->ym : null;
                    if (! $k || trim($k) === '') continue;
                    $map[$k] = (int) ($row->count ?? 0);
                }

                return $map;
            };

            $mJibom = $safeMonthlyCounts($tables['jibom']);
            $mKwrn = $safeMonthlyCounts($tables['kwrn']);
            $mWanTeror = $safeMonthlyCounts($tables['wan_teror']);

            $monthly = [
                'months' => $monthKeys,
                'series' => [
                    'jibom' => array_map(fn($k) => (int) ($mJibom[$k] ?? 0), $monthKeys),
                    'kwrn' => array_map(fn($k) => (int) ($mKwrn[$k] ?? 0), $monthKeys),
                    'wanTeror' => array_map(fn($k) => (int) ($mWanTeror[$k] ?? 0), $monthKeys),
                ],
            ];

            return [
                'totals' => $totals,
                'modules' => $modules,
                'topProvincesAll' => $topProvincesAll,
                'monthly' => $monthly,
                'generatedAt' => now()->toIso8601String(),
            ];
        },
    ])->name('dashboard');

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

    Route::get('api/jibom/indonesia-map-svg', function () {
        $path = resolveIndonesiaMapSvgPath(env('JIBOM_MAP_SVG_PATH'));
        if (! is_string($path) || trim($path) === '') {
            return response()->json(['message' => 'Map file not found.'], 404);
        }

        $svg = file_get_contents($path);
        if (! is_string($svg) || $svg === '') {
            return response()->json(['message' => 'Map file unreadable.'], 500);
        }

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml; charset=UTF-8');
    })->name('api.jibom.indonesia-map-svg');

    Route::get('api/kwrn/indonesia-map-svg', function () {
        $path = resolveIndonesiaMapSvgPath(env('KWRN_MAP_SVG_PATH', env('JIBOM_MAP_SVG_PATH')));
        if (! is_string($path) || trim($path) === '') {
            return response()->json(['message' => 'Map file not found.'], 404);
        }

        $svg = file_get_contents($path);
        if (! is_string($svg) || $svg === '') {
            return response()->json(['message' => 'Map file unreadable.'], 500);
        }

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml; charset=UTF-8');
    })->name('api.kwrn.indonesia-map-svg');

    Route::get('api/wan-teror/indonesia-map-svg', function () {
        $path = resolveIndonesiaMapSvgPath(env('WAN_TEROR_MAP_SVG_PATH', env('JIBOM_MAP_SVG_PATH')));
        if (! is_string($path) || trim($path) === '') {
            return response()->json(['message' => 'Map file not found.'], 404);
        }

        $svg = file_get_contents($path);
        if (! is_string($svg) || $svg === '') {
            return response()->json(['message' => 'Map file unreadable.'], 500);
        }

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml; charset=UTF-8');
    })->name('api.wan-teror.indonesia-map-svg');

    Route::middleware(['role:superadmin,admin,adminvip'])->group(function () {
        Route::get('api/jibom/counts-by-province', function (Request $request) {
            $type = $request->query('type');
            $allowedTypes = ['ancaman', 'temuan', 'ledakan'];
            if (is_string($type) && $type !== '' && ! in_array($type, $allowedTypes, true)) {
                return response()->json(['message' => 'Invalid type.'], 422);
            }

            $query = DB::table('jibom_incidents as ji')
                ->join('reg_provinces as p', 'p.id', '=', 'ji.province_id')
                ->select([
                    'p.id',
                    'p.name',
                    DB::raw('count(*) as count'),
                ])
                ->groupBy('p.id', 'p.name');

            if (is_string($type) && $type !== '') {
                $query->where('ji.incident_type', $type);
            }

            $rows = $query->orderByDesc('count')->get();

            return response()->json(['data' => $rows]);
        })->name('api.jibom.counts-by-province');

        Route::get('jibom', [JibomIncidentController::class, 'index'])->name('jibom.index');
        Route::get('jibom/create', [JibomIncidentController::class, 'create'])->name('jibom.create');
        Route::post('jibom', [JibomIncidentController::class, 'store'])->name('jibom.store');
        Route::get('jibom/{incident}/edit', [JibomIncidentController::class, 'edit'])->name('jibom.edit');
        Route::put('jibom/{incident}', [JibomIncidentController::class, 'update'])->name('jibom.update');
        Route::delete('jibom/{incident}', [JibomIncidentController::class, 'destroy'])->name('jibom.destroy');

        Route::get('api/kwrn/counts-by-province', function (Request $request) {
            $type = $request->query('type');
            $allowedTypes = ['ancaman', 'temuan', 'ledakan'];
            if (is_string($type) && $type !== '' && ! in_array($type, $allowedTypes, true)) {
                return response()->json(['message' => 'Invalid type.'], 422);
            }

            $query = DB::table('kwrn_incidents as ki')
                ->join('reg_provinces as p', 'p.id', '=', 'ki.province_id')
                ->select([
                    'p.id',
                    'p.name',
                    DB::raw('count(*) as count'),
                ])
                ->groupBy('p.id', 'p.name');

            if (is_string($type) && $type !== '') {
                $query->where('ki.incident_type', $type);
            }

            $rows = $query->orderByDesc('count')->get();

            return response()->json(['data' => $rows]);
        })->name('api.kwrn.counts-by-province');

        Route::get('kwrn', [KwrnIncidentController::class, 'index'])->name('kwrn.index');
        Route::get('kwrn/create', [KwrnIncidentController::class, 'create'])->name('kwrn.create');
        Route::post('kwrn', [KwrnIncidentController::class, 'store'])->name('kwrn.store');
        Route::get('kwrn/{incident}/edit', [KwrnIncidentController::class, 'edit'])->name('kwrn.edit');
        Route::put('kwrn/{incident}', [KwrnIncidentController::class, 'update'])->name('kwrn.update');
        Route::delete('kwrn/{incident}', [KwrnIncidentController::class, 'destroy'])->name('kwrn.destroy');

        Route::get('api/wan-teror/counts-by-province', function (Request $request) {
            $type = $request->query('type');
            $allowedTypes = [
                'napiter',
                'ex-napiter',
                'jaringan-terorisme',
                'bullying-perundungan',
                'aksi-teror',
            ];
            if (is_string($type) && $type !== '' && ! in_array($type, $allowedTypes, true)) {
                return response()->json(['message' => 'Invalid type.'], 422);
            }

            $query = DB::table('wan_teror_incidents as wti')
                ->join('reg_provinces as p', 'p.id', '=', 'wti.province_id')
                ->select([
                    'p.id',
                    'p.name',
                    DB::raw('count(*) as count'),
                ])
                ->groupBy('p.id', 'p.name');

            if (is_string($type) && $type !== '') {
                $query->where('wti.incident_type', $type);
            }

            $rows = $query->orderByDesc('count')->get();

            return response()->json(['data' => $rows]);
        })->name('api.wan-teror.counts-by-province');

        Route::get('wan-teror', [WanTerorIncidentController::class, 'index'])->name('wan-teror.index');
        Route::get('wan-teror/create', [WanTerorIncidentController::class, 'create'])->name('wan-teror.create');
        Route::post('wan-teror', [WanTerorIncidentController::class, 'store'])->name('wan-teror.store');
        Route::get('wan-teror/{incident}/edit', [WanTerorIncidentController::class, 'edit'])->name('wan-teror.edit');
        Route::put('wan-teror/{incident}', [WanTerorIncidentController::class, 'update'])->name('wan-teror.update');
        Route::delete('wan-teror/{incident}', [WanTerorIncidentController::class, 'destroy'])->name('wan-teror.destroy');
    });
});

require __DIR__ . '/settings.php';
