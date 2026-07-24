<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\AiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiController extends Controller
{
    public function edit(AiSettings $aiSettings)
    {
        return Inertia::render('settings/Ai', [
            'ai' => $aiSettings->shared(),
        ]);
    }

    public function update(Request $request, AiSettings $aiSettings): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:512'],
            'api_key' => ['required', 'string', 'max:512'],
            'model' => ['required', 'string', 'max:255'],
        ]);

        $aiSettings->update(
            $validated['endpoint'],
            $validated['api_key'],
            $validated['model'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'AI settings updated.']);

        return to_route('ai.edit');
    }
}
