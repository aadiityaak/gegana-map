<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\BrandingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BrandingController extends Controller
{
    public function update(Request $request, BrandingSettings $brandingSettings): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png', 'max:4096'],
            'favicon' => ['nullable', 'image', 'mimes:png', 'max:2048'],
        ]);

        if (! $request->hasFile('logo') && ! $request->hasFile('favicon') && blank($validated['name'] ?? null)) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('No changes to save.')]);
            return to_route('branding.edit');
        }

        try {
            $brandingSettings->update(
                $validated['name'] ?? null,
                $request->file('logo'),
                $request->file('favicon'),
            );
        } catch (\Throwable $e) {
            Log::error('Branding update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Gagal menyimpan branding: :error', ['error' => $e->getMessage()]),
            ]);

            return to_route('branding.edit');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding updated.')]);

        return to_route('branding.edit');
    }
}
