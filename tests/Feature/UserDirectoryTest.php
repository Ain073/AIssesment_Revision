<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AccountFeatureTestCase;

class UserDirectoryTest extends AccountFeatureTestCase
{
    public function test_user_search_treats_identifier_wildcards_literally_and_keeps_program_filters(): void
    {
        $admin = $this->actor('super_admin');
        $target = $this->account(['student']);
        $target->studentProfile->update(['student_number' => 'STU_100%']);
        $otherProgram = $this->account(['student'], 2);
        $otherProgram->studentProfile->update(['student_number' => 'STU_100%OTHER']);
        $similar = $this->account(['student']);
        $similar->studentProfile->update(['student_number' => 'STUA100X']);

        $this->signInAccount($admin)->get(route('super-admin.users', ['tab' => 'students', 'program' => 1, 'search' => '_100%']))
            ->assertOk()
            ->assertViewHas('students', fn ($students) => $students->modelKeys() === [$target->id])
            ->assertViewHas('programs', fn ($programs) => $programs->count() === 3)
            ->assertSee('data-record-search-form', false)
            ->assertDontSee('data-global-search-url', false);
    }

    public function test_super_admin_user_pages_are_independent_and_only_render_current_page_modals(): void
    {
        $admin = $this->actor('super_admin');
        $teacherIds = [];
        $studentIds = [];
        foreach (range(1, 31) as $number) {
            if ($number <= 30) {
                $teacherIds[] = $this->account(['instructor'])->id;
            }
            $studentIds[] = $this->account(['student'])->id;
        }

        // Identical names still have a consistent order across page boundaries.
        $firstPage = $this->signInAccount($admin)->get(route('super-admin.users'));
        $firstPage->assertOk()
            ->assertViewHas('teachers', fn ($users) => $users->total() === 30 && $users->getCollection()->modelKeys() === array_slice($teacherIds, 0, 25))
            ->assertViewHas('students', fn ($users) => $users->total() === 31 && $users->getCollection()->modelKeys() === array_slice($studentIds, 0, 25))
            ->assertViewHas('users', fn ($users) => $users->count() === 50)
            ->assertSee('class="table-switch-count">30</span>', false)
            ->assertSee('class="table-switch-count">31</span>', false)
            ->assertDontSee('id="viewUserModal'.$teacherIds[25].'"', false)
            ->assertDontSee('id="editUserModal'.$studentIds[25].'"', false);

        $secondPage = $this->get(route('super-admin.users', [
            'tab' => 'students', 'teachers_page' => 2, 'students_page' => 2,
        ]), ['X-Requested-With' => 'XMLHttpRequest']);
        $secondPage->assertOk()
            ->assertViewHas('teachers', fn ($users) => $users->currentPage() === 2 && $users->getCollection()->modelKeys() === array_slice($teacherIds, 25))
            ->assertViewHas('students', fn ($users) => $users->currentPage() === 2 && $users->getCollection()->modelKeys() === array_slice($studentIds, 25))
            ->assertViewHas('users', fn ($users) => $users->count() === 11)
            ->assertSee('Showing 26 to 30 of 30 entries')
            ->assertSee('Showing 26 to 31 of 31 entries')
            ->assertSee('id="viewUserModal'.$teacherIds[25].'"', false)
            ->assertSee('id="editUserModal'.$studentIds[25].'"', false)
            ->assertSee('id="deleteUserModal'.$studentIds[25].'"', false)
            ->assertDontSee('id="viewUserModal'.$teacherIds[0].'"', false);

        parse_str(parse_url($secondPage->viewData('teachers')->url(1), PHP_URL_QUERY), $teacherParams);
        parse_str(parse_url($secondPage->viewData('students')->url(1), PHP_URL_QUERY), $studentParams);
        $this->assertSame(['teachers', '1', '2'], [$teacherParams['tab'], $teacherParams['teachers_page'], $teacherParams['students_page']]);
        $this->assertSame(['students', '2', '1'], [$studentParams['tab'], $studentParams['teachers_page'], $studentParams['students_page']]);

        $this->get(route('super-admin.users', ['teachers_page' => 2]))
            ->assertOk()->assertViewHas('students', fn ($users) => $users->currentPage() === 1 && $users->count() === 25);
    }

    public function test_user_search_and_scope_filters_apply_before_pagination_and_remain_in_links(): void
    {
        $admin = $this->actor('super_admin');
        $matchingIds = [];
        foreach (range(1, 30) as $number) {
            $this->account(['student']);
        }
        foreach (range(1, 27) as $number) {
            $student = $this->account(['student']);
            $student->update(['first_name' => 'Matched']);
            $matchingIds[] = $student->id;
        }
        $this->account(['student'], 3)->update(['first_name' => 'Matched']);
        $this->account(['instructor'], 3)->update(['first_name' => 'Matched']);
        $teacher = $this->account(['instructor']);
        $teacher->update(['first_name' => 'Matched']);

        $response = $this->signInAccount($admin)->get(route('super-admin.users', [
            'tab' => 'students', 'search' => 'mAtChEd', 'department' => 1, 'program' => 1,
            'teachers_page' => 1, 'students_page' => 2,
        ]));
        $response->assertOk()
            ->assertViewHas('students', fn ($users) => $users->total() === 27 && $users->getCollection()->modelKeys() === array_slice($matchingIds, 25))
            ->assertViewHas('teachers', fn ($users) => $users->total() === 1 && $users->first()->id === $teacher->id)
            ->assertSee('Showing 26 to 27 of 27 entries');

        parse_str(parse_url($response->viewData('students')->url(1), PHP_URL_QUERY), $params);
        $this->assertSame('mAtChEd', $params['search']);
        $this->assertSame('1', $params['program']);
        $this->assertSame('1', $params['department']);
        $this->assertSame('students', $params['tab']);

        $this->get(route('super-admin.users', ['search' => 'mAtChEd', 'program' => 1]))
            ->assertOk()->assertViewHas('students', fn ($users) => $users->currentPage() === 1 && $users->count() === 25);
    }

    public function test_user_page_recovers_when_an_edit_removes_the_only_remaining_page_record(): void
    {
        $admin = $this->actor('super_admin');
        foreach (range(1, 25) as $number) {
            $this->account(['student']);
        }
        $target = $this->account(['student']);
        $url = route('super-admin.users', ['tab' => 'students', 'program' => 1, 'students_page' => 2]);

        $this->signInAccount($admin)->get($url)->assertOk()
            ->assertViewHas('students', fn ($users) => $users->currentPage() === 2 && $users->first()->id === $target->id);
        $this->putJson(route('super-admin.users.update', $target), array_merge($this->payload($target), ['program_id' => 2]))
            ->assertOk();
        $this->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertViewHas('students', fn ($users) => $users->currentPage() === 1 && $users->total() === 25 && $users->count() === 25)
            ->assertSee('Showing 1 to 25 of 25 entries')
            ->assertDontSee('id="viewUserModal'.$target->id.'"', false);

        $this->get(route('super-admin.users', ['tab' => 'students', 'program' => 3, 'students_page' => 999]))
            ->assertOk()
            ->assertViewHas('students', fn ($users) => $users->currentPage() === 1 && $users->total() === 0 && $users->isEmpty())
            ->assertSee('Showing 0 to 0 of 0 entries')
            ->assertDontSee('aria-label="Students pagination"', false);
    }

    public function test_dean_and_chair_user_search_cannot_return_accounts_outside_their_scope(): void
    {
        $target = $this->account(['instructor']);
        $outside = $this->account(['instructor'], 3);
        $this->signInAccount($this->actor('admin_dean'))->get(route('admin-dean.teachers', ['search' => 'EMP-']))
            ->assertOk()->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $target->id) && ! $teachers->contains('id', $outside->id));
        $this->signInAccount($this->actor('department_chair'))->get(route('department-chair.teachers', ['search' => 'EMP-']))
            ->assertOk()->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $target->id) && ! $teachers->contains('id', $outside->id));
    }

    public function test_program_and_subject_search_remain_inside_the_chairs_department(): void
    {
        DB::table('programs')->where('program_id', 1)->update(['public_id' => (string) Str::uuid()]);
        DB::table('subjects')->insert([
            ['subject_id' => 1, 'public_id' => (string) Str::uuid(), 'department_id' => 1, 'subject_code' => 'IT101', 'subject_name' => 'Shared Topic'],
            ['subject_id' => 2, 'public_id' => (string) Str::uuid(), 'department_id' => 3, 'subject_code' => 'IT102', 'subject_name' => 'Shared Topic'],
        ]);
        $this->signInAccount($this->actor('department_chair'))->get(route('department-chair.programs', ['search' => 'program']))
            ->assertOk()->assertViewHas('programs', fn ($programs) => $programs->modelKeys() === [1]);
        $this->get(route('department-chair.subjects', ['search' => 'sHaReD']))
            ->assertOk()->assertViewHas('subjects', fn ($subjects) => $subjects->modelKeys() === [1]);
    }
}
