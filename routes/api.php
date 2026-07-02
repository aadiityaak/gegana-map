<?php

use App\Http\Controllers\Api\HermesCaseDevelopmentController;
use App\Http\Controllers\Api\HermesIncidentController;
use Illuminate\Support\Facades\Route;

Route::prefix('hermes')->middleware('hermes.auth')->group(function () {
    Route::post('/incidents', [HermesIncidentController::class, 'upsert']);
    Route::get('/incidents/check', [HermesIncidentController::class, 'check']);
    Route::post('/incidents/{id}/developments', [HermesCaseDevelopmentController::class, 'store'])
        ->whereNumber('id');
});
