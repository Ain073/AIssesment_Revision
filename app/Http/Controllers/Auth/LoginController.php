<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\AccountSessionService;
use Illuminate\Http\JsonResponse;
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

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            if (Auth::user()->must_change_password) {
                return redirect()->route('password.setup');
            }

            $redirectPath = $this->redirectPath();

            if ($redirectPath !== null) {
                return redirect()->to($redirectPath);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'This account does not have an assigned portal yet.']);
        }

        $loginEmail = $request->session()->get('login_lockout_email');
        $loginRetryAfter = 0;

        if (is_string($loginEmail) && $loginEmail !== '') {
            $key = $this->throttleKey($request, $loginEmail);
            if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
                $loginRetryAfter = RateLimiter::availableIn($key);
            } else {
                $request->session()->forget('login_lockout_email');
                $loginEmail = null;
            }
        }

        return view('auth.login', compact('loginRetryAfter', 'loginEmail'));
    }

    public function login(Request $request, AccountSessionService $sessions): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            Log::warning('Login attempt temporarily blocked.', $this->throttleLogContext($throttleKey));

            return $this->loginLockoutResponse($request, $throttleKey);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            $attempts = RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);
            Log::warning('Failed login attempt.', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
                'attempts' => $attempts,
                ...$this->throttleLogContext($throttleKey),
            ]);

            if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
                return $this->loginLockoutResponse($request, $throttleKey);
            }

            return $this->loginErrorResponse($request, 'Invalid email or password.');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget('login_lockout_email');
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

            return $this->loginErrorResponse($request, 'Your account is inactive. Please contact the administrator.');
        }

        $sessions->rememberCurrentSession($request, Auth::user());

        Log::info('User logged in successfully.', [
            'user_id' => Auth::id(),
            'email' => Auth::user()->email,
            'ip' => $request->ip(),
        ]);

        try {
            DB::transaction(fn () => AuditLogger::logOrFail('LOGIN', 'Auth', 'User logged in successfully.'));
        } catch (\Throwable $exception) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw $exception;
        }

        $redirectPath = $this->redirectPath();

        if ($redirectPath === null) {
            Log::warning('Login blocked because the account has no assigned portal.', [
                'user_id' => Auth::id(),
                'email' => Auth::user()->email,
                'ip' => $request->ip(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->loginErrorResponse($request, 'This account does not have an assigned portal yet.');
        }

        $redirect = Auth::user()->must_change_password
            ? redirect()->route('password.setup')
            : redirect()->intended($redirectPath);

        return $request->expectsJson()
            ? response()->json(['redirect' => $redirect->getTargetUrl()])
            : $redirect;
    }

    public function logout(Request $request): RedirectResponse
    {
        Log::info('User logged out.', [
            'user_id' => Auth::id(),
            'email' => Auth::user()?->email,
            'ip' => $request->ip(),
        ]);

        AuditLogger::log('LOGOUT', 'Auth', 'User logged out.');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectPath(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $routeName = $user->loadMissing('roles')->portalRouteName();

        return $routeName ? route($routeName) : null;
    }

    private function throttleKey(Request $request, ?string $email = null): string
    {
        return Str::transliterate(Str::lower($email ?? (string) $request->string('email')).'|'.$request->ip());
    }

    private function loginLockoutResponse(Request $request, string $throttleKey): RedirectResponse|JsonResponse
    {
        // Retain only the email so a refreshed page can recheck the server's limiter.
        $request->session()->put('login_lockout_email', (string) $request->string('email'));
        $seconds = RateLimiter::availableIn($throttleKey);

        return $this->loginErrorResponse(
            $request,
            "Too many login attempts. Please try again in {$seconds} seconds.",
            429,
            $seconds,
        );
    }

    private function loginErrorResponse(Request $request, string $message, int $status = 422, ?int $retryAfter = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            // Inactive/unassigned logins invalidate the session; the next AJAX attempt
            // must use the new CSRF token alongside the updated session cookie.
            $payload = ['message' => $message, 'errors' => ['email' => [$message]], 'csrf_token' => csrf_token()];
            if ($retryAfter !== null) {
                $payload['retry_after'] = $retryAfter;
            }
            $response = response()->json($payload, $status);

            return $retryAfter !== null ? $response->header('Retry-After', (string) $retryAfter) : $response;
        }

        return back()->withErrors(['email' => $message])->onlyInput('email');
    }

    private function throttleLogContext(string $throttleKey): array
    {
        $store = config('cache.limiter') ?? config('cache.default');

        return [
            // Compare counters across requests without logging the email or IP address.
            'throttle_bucket' => hash_hmac('sha256', $throttleKey, (string) config('app.key')),
            'cache_store' => $store,
            'cache_driver' => config("cache.stores.{$store}.driver"),
            'stored_attempts' => RateLimiter::attempts($throttleKey),
            'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
        ];
    }
}
