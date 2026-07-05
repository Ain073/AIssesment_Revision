<?php

namespace App\Services;

use App\Models\InstructorProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;

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
        $user = User::create([
            'name' => $this->buildName($data),
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
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
