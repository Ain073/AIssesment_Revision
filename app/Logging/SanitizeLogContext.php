<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;
use Throwable;

class SanitizeLogContext
{
    /**
     * @var array<int, string>
     */
    private array $sensitiveKeys = [
        'actor_id',
        'actor_email',
        'display_name',
        'email',
        'employee_no',
        'first_name',
        'full_name',
        'instructor_id',
        'instructor_profile_id',
        'last_name',
        'middle_name',
        'name',
        'password',
        'profile_id',
        'remember_token',
        'student_id',
        'student_number',
        'student_profile_id',
        'target_email',
        'target_user_id',
        'token',
        'user_email',
        'user_id',
        'user_name',
        'username',
    ];

    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(function (LogRecord $record): LogRecord {
            $context = $this->sanitize($record->context);
            $extra = $this->sanitize($record->extra);

            $context['actor_role'] ??= $this->actorRole();

            return $record->with(context: $context, extra: $extra);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                unset($values[$key]);

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower($key);

        return $normalizedKey === 'id'
            || str_ends_with($normalizedKey, '_id')
            || str_ends_with($normalizedKey, '_ids')
            || str_ends_with($normalizedKey, '_token')
            || str_ends_with($normalizedKey, '_tokens')
            || str_ends_with($normalizedKey, '_key')
            || str_ends_with($normalizedKey, '_keys')
            || in_array($normalizedKey, $this->sensitiveKeys, true);
    }

    private function actorRole(): string
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return 'guest';
            }

            $roles = $user->relationLoaded('roles')
                ? $user->roles
                : $user->roles()->get();

            $roleNames = $roles
                ->pluck('role_name')
                ->filter()
                ->values()
                ->all();

            return $roleNames === [] ? 'unassigned' : implode(', ', $roleNames);
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
