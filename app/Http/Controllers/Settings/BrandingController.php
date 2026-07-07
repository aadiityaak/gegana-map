<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\BrandingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $brandingSettings->update(
            $validated['name'] ?? null,
            $request->file('logo'),
            $request->file('favicon'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding updated.')]);

        return to_route('branding.edit');
    }
}
