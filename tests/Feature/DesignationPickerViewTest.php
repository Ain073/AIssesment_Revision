<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccountFeatureTestCase;

class DesignationPickerViewTest extends AccountFeatureTestCase
{
    public static function designationTypes(): array
    {
        return ['dean' => ['dean'], 'department chair' => ['chair']];
    }

    #[DataProvider('designationTypes')]
    public function test_first_designation_has_a_working_picker_and_records_the_appointment(string $type): void
    {
        $this->createDesignationTables();
        $isDean = $type === 'dean';
        $actor = $this->actor($isDean ? 'super_admin' : 'admin_dean');
        $teacher = $this->account(['instructor']);
        $outside = $this->account(['instructor'], 3);
        $page = route($isDean ? 'super-admin.roles' : 'admin-dean.designations');
        $currentList = $isDean ? 'adminDeans' : 'departmentChairs';
        $availableList = $isDean ? 'availableAdminDeanTeachers' : 'availableDepartmentChairTeachers';
        $pickerId = $isDean ? 'grantAdminDeanModal' : 'grantDepartmentChairModal';
        $confirmationId = $isDean ? 'deanConfirmModal' : 'chairConfirmModal';

        $response = $this->signInAccount($actor)->get($page, ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertViewHas($currentList, fn ($users) => $users->isEmpty())
            ->assertViewHas($availableList, fn ($users) => $users->contains('id', $teacher->id))
            ->assertSee('data-bs-target="#'.$pickerId.'"', false)
            ->assertSee('id="'.$confirmationId.'"', false)
            ->assertSee('data-designation-candidate="'.$type.'"', false)
            ->assertSee('js/designation-picker.js', false)
            ->assertDontSee('onclick="open', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="'.$pickerId.'"'));
        if (! $isDean) {
            $response->assertViewHas($availableList, fn ($users) => ! $users->contains('id', $outside->id));
        }

        $roleName = $isDean ? 'admin_dean' : 'department_chair';
        $grantUrl = $isDean ? route('super-admin.roles.grant') : route('admin-dean.teachers.department-chair.grant', $teacher);
        $this->from($page)->post($grantUrl, $isDean ? ['user_id' => $teacher->id, 'role_name' => $roleName, 'form_mode' => 'grant_admin_dean'] : [])
            ->assertRedirect($page)->assertSessionHasNoErrors();
        $this->assertTrue($teacher->fresh()->hasRole($roleName));
        $this->assertDatabaseHas('designation_details', [
            'instructor_id' => $teacher->instructorProfile->getKey(), 'designation_id' => $isDean ? 1 : 2,
            'department_id' => 1, 'college_id' => 1, 'status' => 'active', 'designated_by' => $actor->id,
        ]);
        $this->get($page)->assertOk()->assertViewHas($currentList, fn ($users) => $users->contains('id', $teacher->id));
        if (! $isDean) {
            $this->post(route('admin-dean.teachers.department-chair.grant', $outside))->assertForbidden();
        }
        $this->assertDatabaseCount('designation_details', 1);
        $grant = AuditLog::where('action', 'DESIGNATE')->sole();
        $grantChange = collect($grant->metadata['changes'])->firstWhere('model', User::class);
        $this->assertNotContains($roleName, $grantChange['before']['roles']);
        $this->assertContains($roleName, $grantChange['after']['roles']);

        $revokeUrl = $isDean ? route('super-admin.roles.revoke') : route('admin-dean.teachers.department-chair.revoke', $teacher);
        $this->from($page)->delete($revokeUrl, $isDean ? ['user_id' => $teacher->id, 'role_name' => $roleName] : [])->assertRedirect($page);
        $revoke = AuditLog::where('action', 'REVOKE')->sole();
        $revokeChange = collect($revoke->metadata['changes'])->firstWhere('model', User::class);
        $this->assertContains($roleName, $revokeChange['before']['roles']);
        $this->assertNotContains($roleName, $revokeChange['after']['roles']);
        $this->assertSame($actor->id, $revoke->user_id);
        $this->assertFalse($teacher->fresh()->hasRole($roleName));
    }

    #[DataProvider('designationTypes')]
    public function test_designation_picker_remains_available_without_eligible_teachers(string $type): void
    {
        $this->createDesignationTables();
        $isDean = $type === 'dean';
        $this->signInAccount($this->actor($isDean ? 'super_admin' : 'admin_dean'))
            ->get(route($isDean ? 'super-admin.roles' : 'admin-dean.designations'))
            ->assertOk()
            ->assertViewHas($isDean ? 'availableAdminDeanTeachers' : 'availableDepartmentChairTeachers', fn ($users) => $users->isEmpty())
            ->assertSee('No available teacher accounts')
            ->assertSee('id="'.($isDean ? 'grantAdminDeanModal' : 'grantDepartmentChairModal').'"', false);
    }

    private function createDesignationTables(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id('designation_id');
            $table->string('designation_name');
        });
        DB::table('designations')->insert([
            ['designation_id' => 1, 'designation_name' => 'Dean'],
            ['designation_id' => 2, 'designation_name' => 'Department Chair'],
        ]);
        Schema::create('designation_details', function (Blueprint $table) {
            $table->id('designation_details_id');
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('designation_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('college_id')->nullable();
            $table->string('academic_year');
            $table->date('effectivity_date');
            $table->date('end_date')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('designated_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }
}
