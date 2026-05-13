<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        then: function () {
            // ── Kiosk routes ───────────────────────────────────────────────
            \Illuminate\Support\Facades\Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/kiosk.php'));
        },
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',



    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'kiosk.device'   => \App\Http\Middleware\ValidateKioskDevice::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // ── ADD THIS: prune expired kiosk tokens every 5 minutes ──────────
        $schedule->command('kiosk:prune')->everyFiveMinutes();
    })
    ->withProviders([
        \App\Providers\PayrollServiceProvider::class,
    ])
    ->withExceptions(function ($exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        });
    })->create();
