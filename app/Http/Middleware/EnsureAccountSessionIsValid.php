<?php

namespace App\Http\Middleware;

use App\Services\AccountSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountSessionIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($user->status !== 'active') {
            return $this->reject($request, 'Your account is inactive. Please contact the administrator.', 'account_inactive', 403);
        }

        $version = (int) $user->session_version;
        // Old sessions predate the version column and belong to version zero.
        // A valid remember-me cookie can establish a new session at the current version.
        $sessionVersion = $request->session()->get(AccountSessionService::VERSION_KEY,
            Auth::guard()->viaRemember() ? $version : 0);

        if ((int) $sessionVersion !== $version) {
            return $this->reject($request, 'Your session has ended. Please sign in again.', 'authentication_required', 401);
        }

        $request->session()->put(AccountSessionService::VERSION_KEY, $version);

        return $next($request);
    }

    private function reject(Request $request, string $message, string $code, int $status): Response
    {
        Auth::logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message, 'code' => $code, 'login_url' => route('login'),
            ], $status)->header('Cache-Control', 'no-store');
        }

        return redirect()->route('login')->withErrors(['email' => $message])
            ->header('Cache-Control', 'no-store');
    }
}
