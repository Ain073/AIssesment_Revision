<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user?->must_change_password
            && ! $request->routeIs('password.setup')
            && ! $request->routeIs('password.setup.update')
            && ! $request->routeIs('logout')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Please change your initial password before using this feature.',
                    'code' => 'password_change_required',
                ], 403)->header('Cache-Control', 'no-store');
            }

            return redirect()->route('password.setup');
        }

        return $next($request);
    }
}
