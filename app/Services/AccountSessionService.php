<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountSessionService
{
    public const VERSION_KEY = 'account_session_version';

    public function rememberCurrentSession(Request $request, User $user): void
    {
        $request->session()->put([
            self::VERSION_KEY => (int) $user->session_version,
            'password_hash_'.Auth::getDefaultDriver() => Auth::guard()->hashPasswordForCookie($user->getAuthPassword()),
        ]);
    }

    public function changePassword(User $user, string $password, ?Request $currentRequest = null): void
    {
        app(AuditTrailRecorder::class)->verifiedPasswordResetActor($user);
        $passwordHash = Hash::make($password);
        $startingVersion = (int) $user->session_version;

        $user->getConnection()->transaction(function () use ($user, $passwordHash, $currentRequest, $startingVersion): void {
            $lockedUser = $user->newQuery()->lockForUpdate()->findOrFail($user->getKey());

            // Reject an authenticated change if another request already revoked this session.
            if ($currentRequest && (int) $lockedUser->session_version !== $startingVersion) {
                throw new AuthenticationException;
            }

            $user->setRawAttributes($lockedUser->getAttributes(), true);
            $user->forceFill([
                'password' => $passwordHash,
                'must_change_password' => false,
                'remember_token' => Str::random(60),
                'session_version' => (int) $lockedUser->session_version + 1,
            ])->save();
        });

        if ($currentRequest) {
            $user->getConnection()->afterCommit(function () use ($currentRequest, $user): void {
                $currentRequest->session()->regenerate(true);
                $this->rememberCurrentSession($currentRequest, $user);
            });
        }
    }

    public function invalidateAllSessions(User $user): void
    {
        $user->getConnection()->transaction(function () use ($user): void {
            $lockedUser = $user->newQuery()->lockForUpdate()->findOrFail($user->getKey());
            $user->setRawAttributes($lockedUser->getAttributes(), true);
            $user->forceFill([
                'session_version' => (int) $lockedUser->session_version + 1,
                'remember_token' => Str::random(60),
            ])->save();
        });
    }
}
