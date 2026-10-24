<?php

namespace Tests\Feature;

use App\Models\College;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\AccountFeatureTestCase;

class CollegeManagementTest extends AccountFeatureTestCase
{
    public function test_college_acronym_migration_preserves_existing_records_and_department_links(): void
    {
        $migration = require database_path('migrations/2026_10_18_000001_add_college_acronym_to_colleges_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('colleges', 'college_acronym'));
        $colleges = DB::table('colleges')->orderBy('college_id')->get()->toArray();
        $departments = DB::table('departments')->orderBy('department_id')->get()->toArray();
        $migration->up();
        $this->assertEquals($colleges, DB::table('colleges')->select('college_id', 'college_name', 'public_id', 'created_at', 'updated_at')->orderBy('college_id')->get()->toArray());
        $this->assertEquals($departments, DB::table('departments')->orderBy('department_id')->get()->toArray());
        $this->assertSame(2, College::whereNull('college_acronym')->count());
        $this->assertSame('First College', College::findOrFail(1)->shortName());
        $this->assertSame('First College', College::findOrFail(1)->displayName());
    }

    public function test_college_acronym_can_be_saved_normalized_or_left_optional(): void
    {
        foreach ([1, 2, 3] as $departmentId) {
            DB::table('departments')->where('department_id', $departmentId)->update(['public_id' => (string) Str::uuid()]);
        }
        $this->signInAccount($this->actor('super_admin'));
        $this->post(route('super-admin.colleges.store'), [
            'college_name' => 'College of Example Studies', 'college_acronym' => '  ces  ',
        ])->assertRedirect(route('super-admin.colleges'))->assertSessionHasNoErrors();
        $college = College::where('college_name', 'College of Example Studies')->sole();
        $this->assertSame('CES', $college->college_acronym);
        $this->assertSame('CES', $college->shortName());
        $this->assertSame('College of Example Studies (CES)', $college->displayName());
        foreach (['College Without Acronym', 'Another College Without Acronym'] as $name) {
            $this->post(route('super-admin.colleges.store'), ['college_name' => $name])
                ->assertRedirect(route('super-admin.colleges'))->assertSessionHasNoErrors();
            $this->assertNull(College::where('college_name', $name)->sole()->college_acronym);
        }
        $this->get(route('super-admin.colleges'))->assertOk()
            ->assertSee('CES')->assertSee('College of Example Studies')
            ->assertSee('name="college_acronym"', false)
            ->assertSee('value="CES"', false);
    }

    public function test_college_acronym_update_keeps_college_identity_and_can_clear_the_optional_value(): void
    {
        $this->signInAccount($this->actor('super_admin'));
        $college = College::findOrFail(1);
        $count = College::count();
        foreach ([
            ['college_name' => 'First College Full Name', 'college_acronym' => 'fc'],
            ['college_name' => 'First College Full Name', 'college_acronym' => 'FC'],
            ['college_name' => 'First College Renamed'],
        ] as $payload) {
            $this->put(route('super-admin.colleges.update', $college), $payload)
                ->assertRedirect(route('super-admin.colleges'))->assertSessionHasNoErrors();
            $this->assertSame('FC', $college->fresh()->college_acronym);
        }
        $this->assertSame($count, College::count());
        $this->assertSame(2, DB::table('departments')->where('college_id', $college->college_id)->count());
        $this->put(route('super-admin.colleges.update', $college), [
            'college_name' => 'First College Renamed', 'college_acronym' => '',
        ])->assertRedirect(route('super-admin.colleges'))->assertSessionHasNoErrors();
        $this->assertNull($college->fresh()->college_acronym);
        $this->assertSame('First College Renamed', $college->fresh()->shortName());
    }

    public function test_college_acronym_validation_rejects_duplicates_and_invalid_values_without_saving(): void
    {
        College::findOrFail(1)->update(['college_acronym' => 'FC']);
        $other = College::findOrFail(2);
        $this->signInAccount($this->actor('super_admin'));
        foreach ([' fc ', str_repeat('X', 51), ['not-a-string']] as $value) {
            $this->postJson(route('super-admin.colleges.store'), [
                'college_name' => 'Rejected College', 'college_acronym' => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors('college_acronym');
            $this->putJson(route('super-admin.colleges.update', $other), [
                'college_name' => 'Rejected Rename', 'college_acronym' => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors('college_acronym');
        }
        $this->assertSame(2, College::count());
        $this->assertSame('Other College', $other->fresh()->college_name);
        $this->assertNull($other->fresh()->college_acronym);
        // The database constraint also protects writes that bypass HTTP validation.
        $this->expectException(QueryException::class);
        $other->update(['college_acronym' => 'FC']);
    }

    public function test_college_search_matches_acronym_and_full_name_with_or_without_ajax(): void
    {
        foreach ([1, 2, 3] as $departmentId) {
            DB::table('departments')->where('department_id', $departmentId)->update(['public_id' => (string) Str::uuid()]);
        }
        College::findOrFail(1)->update(['college_acronym' => 'FC']);
        $this->signInAccount($this->actor('super_admin'));
        foreach (['fc', 'First College'] as $term) {
            foreach ([[], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
                $this->get(route('super-admin.colleges', ['search' => $term]), $headers)->assertOk()
                    ->assertViewHas('colleges', fn ($colleges) => $colleges->modelKeys() === [1])
                    ->assertViewHas('departments', fn ($departments) => $departments->modelKeys() === [1, 2]);
            }
        }
    }
}
