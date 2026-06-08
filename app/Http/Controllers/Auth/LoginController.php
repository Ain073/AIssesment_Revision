<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($this->redirectPath());
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors([
                    'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
                ])
                ->onlyInput('email');
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);
            Log::warning('Failed login attempt.', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        if (Auth::user()->status !== 'active') {
            Log::warning('Inactive account login blocked.', [
                'user_id' => Auth::id(),
                'email' => Auth::user()->email,
                'ip' => $request->ip(),
            ]);

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account is inactive. Please contact the administrator.'])
                ->onlyInput('email');
        }

        Log::info('User logged in successfully.', [
            'user_id' => Auth::id(),
            'email' => Auth::user()->email,
            'ip' => $request->ip(),
        ]);

        return redirect()->intended($this->redirectPath());
    }

    public function logout(Request $request): RedirectResponse
    {
        Log::info('User logged out.', [
            'user_id' => Auth::id(),
            'email' => Auth::user()?->email,
            'ip' => $request->ip(),
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectPath(): string
    {
        $role = DB::table('roles')
            ->join('user_roles', 'roles.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', Auth::id())
            ->value('roles.role_name');

        return match ($role) {
            'super_admin' => route('super-admin.dashboard'),
            default => route('login'),
        };
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->string('email')).'|'.$request->ip());
    }
}
