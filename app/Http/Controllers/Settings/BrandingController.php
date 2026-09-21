<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\BrandingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    public function edit(BrandingSettings $brandingSettings): Response
    {
        return Inertia::render('settings/Branding', [
            'library' => $brandingSettings->library(),
            'aktif' => $brandingSettings->aktif(),
        ]);
    }

    public function update(Request $request, BrandingSettings $brandingSettings): RedirectResponse
    {
        $jenisPerField = [
            'logo_path' => 'logo',
            'favicon_path' => 'favicon',
        ];

        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png', 'max:4096'],
            'favicon' => ['nullable', 'image', 'mimes:png', 'max:2048'],
        ];

        foreach ($jenisPerField as $field => $jenis) {
            $rules[$field] = ['nullable', 'string', 'max:255', Rule::in($brandingSettings->pathTersedia($jenis))];
        }

        $validated = $request->validate($rules, [
            'logo_path.in' => __('Gambar logo yang dipilih tidak lagi tersedia.'),
            'favicon_path.in' => __('Gambar favicon yang dipilih tidak lagi tersedia.'),
        ]);

        $logoPath = $validated['logo_path'] ?? null;
        $faviconPath = $validated['favicon_path'] ?? null;

        if (
            ! $request->hasFile('logo') && ! $request->hasFile('favicon')
            && blank($logoPath) && blank($faviconPath)
            && blank($validated['name'] ?? null)
        ) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('No changes to save.')]);
            return to_route('branding.edit');
        }

        try {
            $brandingSettings->update(
                $validated['name'] ?? null,
                $request->file('logo'),
                $request->file('favicon'),
                $logoPath,
                $faviconPath,
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

    public function hapusMedia(Request $request, BrandingSettings $brandingSettings): RedirectResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        if (! $brandingSettings->hapusMedia($validated['path'])) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Gambar tidak bisa dihapus (sedang aktif atau bukan bagian riwayat).'),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gambar dihapus dari riwayat.')]);

        return back();
    }
}
