<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->authenticateSessions();
        $middleware->web(append: [\App\Http\Middleware\EnsureAccountSessionIsValid::class]);

        $middleware->alias([
            'admin_dean' => \App\Http\Middleware\EnsureAdminDean::class,
            'department_chair' => \App\Http\Middleware\EnsureDepartmentChair::class,
            'instructor' => \App\Http\Middleware\EnsureInstructor::class,
            'no_cache' => \App\Http\Middleware\PreventBrowserCache::class,
            'password_changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
            'student' => \App\Http\Middleware\EnsureStudent::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has ended. Please sign in again.',
                    'code' => 'authentication_required',
                    'login_url' => route('login'),
                ], 401)->header('Cache-Control', 'no-store');
            }

            return null;
        });
    })->create();
