<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', \App\Http\Middleware\CheckModuleAccess::class);

        // "May you open this project at all?" — every route carrying a project
        // or a job site, so one added later is guarded the moment it is written.
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureScopeIsVisible::class);
        $middleware->alias([
            // The 'admin' alias lived here until F2, retired one pass at a
            // time as each module moved its routes onto 'ability'.

            // ability:expenses.view          — a company-wide screen
            // ability:expenses.view,project  — scoped to a route parameter
            'ability' => \App\Http\Middleware\EnsureUserHasAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
