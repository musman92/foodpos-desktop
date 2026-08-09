<?php

use Illuminate\Support\Facades\Route;

/*
| Cloud print bridge API — not used in offline edition (Tauri prints locally).
| Kept empty so SaaS route file shape remains familiar if you compare trees.
*/
if (! config('offline.enabled')) {
    $controller = \App\Http\Controllers\DesktopPrintApiController::class;
    $middleware = \App\Http\Middleware\AuthenticateBranchDesktopKey::class;

    Route::prefix('desktop')
        ->middleware($middleware)
        ->group(function () use ($controller) {
            Route::get('/status', [$controller, 'status']);
            Route::get('/config', [$controller, 'config']);
            Route::post('/heartbeat', [$controller, 'heartbeat']);
            Route::post('/system-printers', [$controller, 'systemPrinters']);
            Route::post('/broadcasting/auth', [$controller, 'broadcastingAuth']);
            Route::get('/print-jobs/pending', [$controller, 'pendingJobs']);
            Route::post('/print-jobs/{printJob}/ack', [$controller, 'acknowledge']);
        });
}
