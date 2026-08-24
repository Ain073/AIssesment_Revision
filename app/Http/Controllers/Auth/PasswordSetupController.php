<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordSetupController extends Controller
{
    public function edit(): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->must_change_password) {
            return redirect()->route($user->portalRouteName() ?? 'profile.show');
        }

        return view('auth.password-setup', [
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        Log::info('User completed first-login password setup.', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route($user->portalRouteName() ?? 'profile.show')
            ->with('status', 'Password updated successfully.');
    }
}
