<?php

namespace Tests\Feature;

use App\Models\Subject;
use Tests\TestCase;

class SubjectModelTest extends TestCase
{
    public function test_subject_relations_and_queries_resolve_correctly(): void
    {
        $subject = new Subject();
        $subject->setRawAttributes([
            'subject_id' => 1,
            'department_id' => 2,
            'subject_code' => 'CS 101',
            'subject_name' => 'Intro to CS',
            'is_active' => true,
        ], true);

        $this->assertSame(1, $subject->subject_id);
        $this->assertStringContainsString('departments', $subject->department()->toSql());
        $this->assertStringContainsString('class_details', $subject->classDetails()->toSql());
        $this->assertStringContainsString('classes', $subject->classes()->toSql());
        $this->assertStringContainsString('assessments', $subject->assessments()->toSql());

        $sql = Subject::with(['department.college'])->where('department_id', 2)->toSql();
        $this->assertStringContainsString('department_id', $sql);
        $this->assertStringContainsString('=', $sql);
    }
}
