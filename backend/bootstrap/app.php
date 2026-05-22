<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // No SPA-stateful guard: the public catalogue is anonymous and the
        // admin-only /api/admin/* endpoints opt-in to session auth via the
        // `web` middleware group inside routes/api.php.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
