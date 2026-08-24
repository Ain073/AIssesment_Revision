<?php

namespace App\Services;

use App\Mail\InitialAccountPasswordMail;
use App\Models\InstructorProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class UserAccountService
{
    public const BASE_ROLES = [
        'instructor',
        'student',
    ];

    public const MANAGED_ROLES = [
        'admin_dean',
        'department_chair',
    ];

    public function __construct(private NotificationService $notifications)
    {
    }

    public function createAccount(array $data, array $managedRoles = []): User
    {
        $data['password'] = $data['password'] ?? $this->initialPasswordFor($data);

        $user = User::create([
            'name' => $this->buildName($data),
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'must_change_password' => true,
            'status' => $data['status'],
        ]);

        $this->syncRoles($user, $data['base_role'], $managedRoles);
        $this->syncInstructorProfile($user, $data);
        $this->syncStudentProfile($user, $data);

        return $user;
    }

    public function updateAccount(User $user, array $data, array $managedRoles = []): void
    {
        $user->fill([
            'name' => $this->buildName($data),
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'status' => $data['status'],
        ]);

        $user->save();

        $this->syncRoles($user, $data['base_role'], $managedRoles);
        $this->syncInstructorProfile($user, $data);
        $this->syncStudentProfile($user, $data);
    }

    public function sendAccountCreatedNotification(User $user): void
    {
        $this->notifications->send(
            $user,
            'Account Created',
            'Your AIssessment account has been created.',
            $user->portalRouteName() ? route($user->portalRouteName()) : route('login'),
            'account'
        );
    }

    public function sendInitialPasswordEmail(User $user, string $initialPassword): bool
    {
        try {
            Mail::to($user->email)->send(new InitialAccountPasswordMail($user, $initialPassword));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Initial account password email could not be sent.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function initialPasswordFor(array $data): string
    {
        $identifier = $data['base_role'] === 'instructor'
            ? (string) ($data['employee_number'] ?? '')
            : (string) ($data['student_number'] ?? '');
        $digits = preg_replace('/\D+/', '', $identifier) ?? '';

        return Str::upper(
            Str::substr(Str::ascii(trim((string) ($data['first_name'] ?? ''))), 0, 1)
            .Str::substr(Str::ascii(trim((string) ($data['last_name'] ?? ''))), 0, 1)
            .Str::substr($digits, -3)
        );
    }

    public static function identifierPasswordDigitsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

            if (strlen($digits) < 3) {
                $fail('The '.str_replace('_', ' ', $attribute).' must contain at least 3 digits for the generated initial password.');
            }
        };
    }

    public function buildName(array $data): string
    {
        return collect([
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
        ])->filter()->implode(' ');
    }

    private function syncRoles(User $user, string $baseRole, array $managedRoles = []): void
    {
        $managedRoles = collect($managedRoles)->unique()->values();

        $roleIds = Role::query()
            ->whereIn('role_name', [...self::BASE_ROLES, ...self::MANAGED_ROLES])
            ->pluck('role_id', 'role_name');

        $currentRoleIds = $user->roles()->pluck('roles.role_id');
        $baseRoleIds = $roleIds->only(self::BASE_ROLES)->values();
        $managedRoleIds = $roleIds->only(self::MANAGED_ROLES)->values();
        $unmanagedRoleIds = $currentRoleIds->diff($baseRoleIds)->diff($managedRoleIds);
        $rolesToKeep = collect([$roleIds->get($baseRole)])->filter();

        if ($baseRole === 'instructor') {
            $rolesToKeep = $rolesToKeep->merge(
                $managedRoles
                    ->map(fn (string $roleName) => $roleIds->get($roleName))
                    ->filter()
                    ->values()
            );
        }

        $user->roles()->sync($unmanagedRoleIds->merge($rolesToKeep)->unique()->all());
    }

    private function syncInstructorProfile(User $user, array $data): void
    {
        if ($data['base_role'] !== 'instructor') {
            $user->instructorProfile()?->delete();

            return;
        }

        InstructorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'department_id' => $data['department_id'],
                'employee_number' => $data['employee_number'],
            ],
        );
    }

    private function syncStudentProfile(User $user, array $data): void
    {
        if ($data['base_role'] !== 'student') {
            $user->studentProfile()?->delete();

            return;
        }

        StudentProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'program_id' => $data['program_id'],
                'student_number' => $data['student_number'],
            ],
        );
    }
}
