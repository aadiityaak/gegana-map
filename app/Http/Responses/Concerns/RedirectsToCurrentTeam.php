<?php

namespace App\Http\Responses\Concerns;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam($request, string $redirect): string
    {
        if ($redirect === '') {
            return '/';
        }

        return str_starts_with($redirect, '/') ? $redirect : "/{$redirect}";
    }
}
