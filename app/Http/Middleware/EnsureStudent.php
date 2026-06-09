<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudent
{
    /**
     * Ensure the request comes from an active student account.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your account is inactive. Please contact the administrator.']);
        }

        $hasStudentAccess = $user->roles()
            ->where('role_name', 'student')
            ->exists();

        if (! $hasStudentAccess || $user->hasRole('super_admin')) {
            Log::warning('Unauthorized student portal access attempt.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            abort(403, 'You are not authorized to access this area.');
        }

        return $next($request);
    }
}
