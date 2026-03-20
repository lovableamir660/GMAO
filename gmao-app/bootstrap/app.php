<?php

use App\Http\Middleware\SetSiteContext;
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
        $middleware->statefulApi();
        
        // Exclure les routes API du CSRF
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        
        // Ajouter le middleware pour les routes API authentifiées
        $middleware->appendToGroup('api', [
            SetSiteContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
