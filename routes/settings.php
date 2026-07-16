<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\BrandingController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::redirect('settings/appearance', '/settings/profile');
    Route::inertia('settings/branding', 'settings/Branding')->name('branding.edit');
    Route::post('settings/branding', [BrandingController::class, 'update'])->name('branding.update');
    Route::inertia('settings/about', 'settings/About', [
        'app' => fn () => [
            'version' => env('APP_VERSION', '1.0.0'),
            'environment' => config('app.env'),
        ],
        'backend' => fn () => [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'composer' => (function (): array {
                $path = base_path('composer.json');
                if (! File::exists($path)) return [];
                $json = json_decode(File::get($path), true);
                if (! is_array($json)) return [];
                $require = $json['require'] ?? [];
                $requireDev = $json['require-dev'] ?? [];
                return [
                    'require' => is_array($require) ? $require : [],
                    'require_dev' => is_array($requireDev) ? $requireDev : [],
                ];
            })(),
        ],
        'frontend' => fn () => [
            'node' => null,
            'package' => (function (): array {
                $path = base_path('package.json');
                if (! File::exists($path)) return [];
                $json = json_decode(File::get($path), true);
                if (! is_array($json)) return [];
                $dependencies = $json['dependencies'] ?? [];
                $devDependencies = $json['devDependencies'] ?? [];
                return [
                    'dependencies' => is_array($dependencies) ? $dependencies : [],
                    'dev_dependencies' => is_array($devDependencies) ? $devDependencies : [],
                ];
            })(),
        ],
    ])->name('about.edit');
});
