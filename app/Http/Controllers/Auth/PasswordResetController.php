<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower($validated['email']);
        $user = User::query()->where('email', $email)->first();

        if (! $user || $user->status !== 'active') {
            Log::notice('Password reset link requested for unavailable account.', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_id' => $user?->id,
                'status' => $user?->status,
            ]);

            return back()
                ->with('status', $this->resetLinkResponseMessage())
                ->onlyInput('email');
        }

        $status = Password::sendResetLink(['email' => $email]);

        Log::info('Password reset link requested.', [
            'email' => $email,
            'ip' => $request->ip(),
            'status' => $status,
            'user_id' => $user->id,
        ]);

        return back()
            ->with('status', $this->resetLinkResponseMessage())
            ->onlyInput('email');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) use ($request): void {
                if ($user->status !== 'active') {
                    Log::warning('Password reset blocked for inactive account.', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'ip' => $request->ip(),
                    ]);

                    throw ValidationException::withMessages([
                        'email' => 'Your account is inactive. Please contact the administrator.',
                    ]);
                }

                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                Log::info('User reset password successfully.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. You may now log in.');
    }

    private function resetLinkResponseMessage(): string
    {
        return 'If an active account exists for that email, a password reset link has been sent.';
    }
}
