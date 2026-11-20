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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class]);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureDailyBackup::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\ActiveYear::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Business-rule violations (abort 422, e.g. "payroll is finalized") go back with a flash message.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($e->getStatusCode() === 422 && ! $request->expectsJson()) {
                return back()->with('error', $e->getMessage() ?: 'This action is not allowed.');
            }
        });
    })->create();
