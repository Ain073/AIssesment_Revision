<?php

use App\Http\Middleware\EnsureAccountSessionIsValid;
use App\Http\Middleware\EnsureAdminDean;
use App\Http\Middleware\EnsureDepartmentChair;
use App\Http\Middleware\EnsureInstructor;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureStudent;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\PreventBrowserCache;
use App\Http\Middleware\RecordAuditTrail;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $middleware->web(append: [
            EnsureAccountSessionIsValid::class,
            RecordAuditTrail::class,
        ]);
        $middleware->appendToPriorityList(
            after: Authorize::class,
            append: RecordAuditTrail::class,
        );

        $middleware->alias([
            'admin_dean' => EnsureAdminDean::class,
            'department_chair' => EnsureDepartmentChair::class,
            'instructor' => EnsureInstructor::class,
            'no_cache' => PreventBrowserCache::class,
            'password_changed' => EnsurePasswordIsChanged::class,
            'student' => EnsureStudent::class,
            'super_admin' => EnsureSuperAdmin::class,
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
