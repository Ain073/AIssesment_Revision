<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccountFeatureTestCase;

class UserAvailabilityTest extends AccountFeatureTestCase
{
    #[DataProvider('accountUpdates')]
    public function test_live_availability_excludes_only_the_authorized_edited_account(string $portal, string $actorRole, string $baseRole): void
    {
        $actor = $this->actor($actorRole);
        $target = $this->account([$baseRole]);
        $other = $this->account([$baseRole]);
        $this->signInAccount($actor);
        $field = $baseRole === 'instructor' ? 'employee_number' : 'student_number';
        $profile = $baseRole === 'instructor' ? 'instructorProfile' : 'studentProfile';
        foreach (['email' => $target->email, $field => $target->$profile->$field] as $name => $value) {
            $this->postJson(route("{$portal}.users.availability.edit", $target), ['field' => $name, 'value' => $value])
                ->assertOk()->assertJsonPath('available', true);
        }
        foreach (['email' => $other->email, $field => $other->$profile->$field] as $name => $value) {
            $this->postJson(route("{$portal}.users.availability.edit", $target), [
                'field' => $name, 'value' => $value, 'user_id' => $other->id, 'ignore' => $other->id,
            ])->assertOk()->assertJsonPath('available', false);
        }
        $this->assertSame('Original Person', $target->fresh()->name);
    }

    public function test_live_availability_does_not_allow_excluding_accounts_outside_the_editors_scope(): void
    {
        foreach (['admin-dean' => 'admin_dean', 'department-chair' => 'department_chair'] as $portal => $role) {
            $this->signInAccount($this->actor($role));
            foreach (['instructor', 'student'] as $baseRole) {
                $outside = $this->account([$baseRole], 3);
                $this->postJson(route("{$portal}.users.availability.edit", $outside), [
                    'field' => 'email', 'value' => $outside->email,
                ])->assertForbidden();
            }
        }
        $chair = $this->actor('department_chair');
        $sameCollegeOtherDepartment = $this->account(['student'], 2);
        $this->signInAccount($chair)->postJson(route('department-chair.users.availability.edit', $sameCollegeOtherDepartment), [
            'field' => 'email', 'value' => $sameCollegeOtherDepartment->email,
        ])->assertForbidden();
    }

    public function test_live_availability_requires_valid_input_and_an_authorized_active_account(): void
    {
        $url = route('super-admin.users.availability');
        $this->postJson($url, ['field' => 'email', 'value' => 'test@example.test'])->assertUnauthorized();
        $admin = $this->actor('super_admin');
        $this->signInAccount($admin);
        foreach ([
            ['field' => 'password', 'value' => 'secret'],
            ['field' => ['email'], 'value' => 'test@example.test'],
            ['field' => 'email', 'value' => 'not-an-email'],
            ['field' => 'student_number', 'value' => ['STU-123']],
            ['field' => 'student_number', 'value' => str_repeat('x', 256)],
            ['field' => 'email', 'value' => ''],
        ] as $payload) {
            $this->postJson($url, $payload)->assertUnprocessable();
        }
        $this->postJson(route('super-admin.users.availability.edit', $admin), [
            'field' => 'email', 'value' => $admin->email,
        ])->assertForbidden();
        $this->signInAccount($this->account(['student']))->postJson($url, [
            'field' => 'email', 'value' => 'test@example.test',
        ])->assertForbidden();
        $admin->update(['status' => 'inactive']);
        $this->signInAccount($admin)->postJson($url, ['field' => 'email', 'value' => 'test@example.test'])
            ->assertForbidden();
    }

    public function test_live_availability_requires_an_assigned_academic_scope_and_limits_requests(): void
    {
        foreach (['admin-dean' => 'admin_dean', 'department-chair' => 'department_chair'] as $portal => $role) {
            $actor = $this->actor($role);
            $actor->instructorProfile->delete();
            $actor->refresh(); // Real requests reload the actor instead of retaining the deleted relation.
            $this->signInAccount($actor)->postJson(route("{$portal}.users.availability"), [
                'field' => 'email', 'value' => 'test@example.test',
            ])->assertForbidden();
        }
        $this->signInAccount($this->actor('super_admin'));
        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('super-admin.users.availability'), ['field' => 'email', 'value' => 'unused@example.test'])
                ->assertOk();
        }
        $this->postJson(route('super-admin.users.availability'), ['field' => 'email', 'value' => 'unused@example.test'])
            ->assertTooManyRequests();
        // The availability limit must not consume the separate save endpoint's allowance.
        $this->putJson(route('super-admin.users.update', $this->account(['student'])), ['email' => 'invalid'])
            ->assertUnprocessable();
    }

    public static function availabilityFormPages(): array
    {
        // The Dean currently has a Teachers page; there is no student directory in that portal.
        return array_filter(self::accountUpdates(), fn (array $case) => $case[0] !== 'admin-dean' || $case[2] !== 'student');
    }

    #[DataProvider('availabilityFormPages')]
    public function test_live_availability_urls_are_present_in_ajax_loaded_create_and_edit_forms(string $portal, string $actorRole, string $baseRole): void
    {
        $target = $this->account([$baseRole]);
        $page = $portal === 'super-admin' ? 'users'
            : ($portal === 'department-chair' && $baseRole === 'student' ? 'students' : 'teachers');
        $response = $this->signInAccount($this->actor($actorRole))->get(route("{$portal}.{$page}"), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();
        foreach ([
            'js/user-availability.js',
            'data-user-availability-url="'.route("{$portal}.users.availability").'"',
            'data-user-availability-url="'.route("{$portal}.users.availability.edit", $target).'"',
        ] as $expected) {
            $this->assertTrue(str_contains($response->getContent(), $expected), "Missing availability wiring on {$portal} {$baseRole} form: {$expected}");
        }
    }
}
