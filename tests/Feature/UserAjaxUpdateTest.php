<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccountFeatureTestCase;

class UserAjaxUpdateTest extends AccountFeatureTestCase
{
    #[DataProvider('accountUpdates')]
    public function test_ajax_form_updates_account_and_returns_json(string $portal, string $actorRole, string $baseRole): void
    {
        $actor = $this->actor($actorRole);
        $target = $this->account([$baseRole]);
        $password = $target->password;

        $this->signInAccount($actor)->post(route("{$portal}.users.update", $target), [
            ...$this->payload($target),
            '_method' => 'PUT',
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['message' => 'User account updated successfully.']);

        $target->refresh();
        $this->assertSame('Updated Person', $target->name);
        $this->assertSame($password, $target->password);
        $this->assertTrue($target->hasRole($baseRole));
        if ($baseRole === 'instructor') {
            $this->assertSame(1, $target->instructorProfile->department_id);
        } else {
            $this->assertSame(1, $target->studentProfile->program_id);
        }
        if ($actorRole === 'super_admin') {
            $identifierLabel = $baseRole === 'student' ? 'Student No.' : 'Employee No.';
            $identifier = $baseRole === 'student' ? $target->studentProfile->student_number : $target->instructorProfile->employee_number;
            $description = "Updated {$baseRole} account for Updated Person ({$identifierLabel}: {$identifier}; Status: active)";
            $log = AuditLog::where('action', 'UPDATE')->sole();
            $this->assertSame($actor->id, $log->user_id);
            $this->assertSame($target->id, $log->auditable_id);
            $this->assertSame($description, $log->displayDescription());
            $target->update(['first_name' => 'Later']);
            $target->delete();
            $this->assertSame($description, $log->fresh()->displayDescription());
        }
    }

    #[DataProvider('portals')]
    public function test_invalid_ajax_update_returns_422_without_saving(string $portal, string $actorRole): void
    {
        $actor = $this->actor($actorRole);
        $target = $this->account(['instructor']);
        $payload = $this->payload($target);
        $payload['email'] = $actor->email;

        $this->signInAccount($actor)->putJson(route("{$portal}.users.update", $target), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame('Original Person', $target->fresh()->name);
    }

    public function test_dean_and_chair_cannot_update_users_outside_their_scope(): void
    {
        $target = $this->account(['instructor'], 3);
        foreach (['admin-dean' => 'admin_dean', 'department-chair' => 'department_chair'] as $portal => $role) {
            $this->signInAccount($this->actor($role))->putJson(route("{$portal}.users.update", $target), $this->payload($target))
                ->assertForbidden();
        }
        $this->assertSame('Original Person', $target->fresh()->name);
    }

    public function test_super_admin_designation_conflict_returns_json_validation_error(): void
    {
        $admin = $this->actor('super_admin');
        $this->actor('admin_dean');
        $target = $this->account(['instructor']);

        $this->signInAccount($admin)->putJson(route('super-admin.users.update', $target), [
            ...$this->payload($target),
            'authorizations' => ['admin_dean'],
        ])->assertUnprocessable()->assertJsonValidationErrors('authorizations');
        $this->assertFalse($target->fresh()->hasRole('admin_dean'));
    }

    public function test_dean_cannot_move_a_chair_to_an_occupied_department(): void
    {
        $dean = $this->actor('admin_dean');
        $target = $this->account(['instructor', 'department_chair']);
        $this->account(['instructor', 'department_chair'], 2);

        $this->signInAccount($dean)->putJson(route('admin-dean.users.update', $target), [
            ...$this->payload($target),
            'department_id' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('authorizations');
        $this->assertSame(1, $target->fresh()->instructorProfile->department_id);
    }

    public function test_html_fallback_keeps_the_users_tab_and_filters_in_the_redirect(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $previousUrl = route('super-admin.users', ['tab' => 'students', 'department' => 1, 'program' => 1]);

        $this->signInAccount($admin)->from($previousUrl)->put(route('super-admin.users.update', $target), $this->payload($target))
            ->assertRedirect($previousUrl)->assertSessionHas('status', 'User account updated successfully.');
    }

    public function test_super_admin_can_switch_a_student_to_teacher_through_ajax(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $payload = $this->payload($target);
        unset($payload['program_id'], $payload['student_number']);

        $this->signInAccount($admin)->putJson(route('super-admin.users.update', $target), [
            ...$payload,
            'base_role' => 'instructor',
            'department_id' => 2,
            'employee_number' => 'NEW-123',
        ])->assertOk();

        $target->refresh();
        $this->assertTrue($target->hasRole('instructor'));
        $this->assertFalse($target->hasRole('student'));
        $this->assertNull($target->studentProfile);
        $this->assertSame(2, $target->instructorProfile->department_id);
    }

    public function test_ajax_refresh_renders_updated_rows_and_working_edit_forms_with_the_same_filters(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $this->signInAccount($admin)->putJson(route('super-admin.users.update', $target), $this->payload($target))->assertOk();

        $this->get(route('super-admin.users', ['tab' => 'students', 'department' => 1, 'program' => 1]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertViewHas('selectedDepartmentKey', '1')
            ->assertViewHas('selectedProgramKey', '1')
            ->assertSee('Updated Person')
            ->assertSee('value="Updated"', false)
            ->assertSee('js/user-directory.js', false)
            ->assertSee('data-ajax-form data-reload-page-on-success="true" data-preserve-page-state="true"', false);
    }
}
