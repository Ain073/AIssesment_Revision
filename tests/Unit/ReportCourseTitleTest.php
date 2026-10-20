<?php

namespace Tests\Unit;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\ClassDetail;
use App\Models\PublishAssessment;
use App\Models\Subject;
use App\Support\ReportCourseTitle;
use Tests\TestCase;

class ReportCourseTitleTest extends TestCase
{
    public function test_different_subjects_in_the_same_section_remain_separate(): void
    {
        $first = $this->published(1, 'IC1', 'Personal Development', 'BSIT 2-A');
        $second = $this->published(2, 'ELEC2', 'Mobile Development', 'BSIT 2-A');

        $this->assertSame('IC1 - BSIT 2-A / Personal Development | ELEC2 - BSIT 2-A / Mobile Development', ReportCourseTitle::forAssessments(collect([$first, $second])));
        $this->assertSame('IC1 - BSIT 2-A / Personal Development', ReportCourseTitle::forAssessment($first));
        $this->assertSame('ELEC2 - BSIT 2-A / Mobile Development', ReportCourseTitle::forAssessment($second));
    }

    public function test_one_subject_combines_only_the_selected_sections_and_deduplicates_quizzes(): void
    {
        $sectionA = $this->published(1, 'IT101', 'Computing', 'BSIT 1-A');
        $sectionB = $this->published(1, 'IT101', 'Computing', 'BSIT 1-B');
        $anotherQuiz = $this->published(1, 'IT101', 'Computing', 'BSIT 1-A');

        $this->assertSame('IT101 - BSIT 1-A, BSIT 1-B / Computing', ReportCourseTitle::forAssessments(collect([$sectionA, $sectionB, $anotherQuiz])));
        $this->assertSame('IT101 - BSIT 1-A / Computing', ReportCourseTitle::forAssessments(collect([$sectionA, $anotherQuiz])));
        $this->assertSame('IT101 - BSIT 1-B / Computing', ReportCourseTitle::forAssessment($sectionB));
    }

    public function test_class_subject_fallback_and_missing_metadata_are_supported(): void
    {
        $published = $this->published(1, 'IT101', 'Computing', 'BSIT 1-A');
        $published->assessment->setRelation('subject', null);
        $this->assertSame('IT101 - BSIT 1-A / Computing', ReportCourseTitle::forAssessment($published));

        $published->setRelation('classDetail', null);
        $this->assertSame('No code / No subject', ReportCourseTitle::forAssessment($published));
        $this->assertSame('', ReportCourseTitle::forAssessments(collect()));
    }

    private function published(int $subjectId, string $code, string $title, string $section): PublishAssessment
    {
        $subject = (new Subject)->forceFill(['subject_id' => $subjectId, 'subject_code' => $code, 'subject_name' => $title]);
        $assessment = (new Assessment)->setRelation('subject', $subject);
        $class = (new AcademicClass)->forceFill(['year_level' => 1, 'section_name' => $section])->setRelation('subject', $subject);
        $detail = (new ClassDetail)->setRelation('class', $class);

        return (new PublishAssessment)->setRelation('assessment', $assessment)->setRelation('classDetail', $detail);
    }
}
