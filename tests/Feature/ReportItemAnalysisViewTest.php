<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\Assessment;
use App\Models\PublishAssessment;
use App\Models\Report;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ReportItemAnalysisViewTest extends TestCase
{
    public function test_both_report_types_show_all_items_and_independent_quiz_panels_when_finalized(): void
    {
        foreach (['formative', 'summative'] as $type) {
            $rows = collect([1, 2])->map(function (int $quiz) use ($type): array {
                $items = collect(range(1, 5))->map(fn (int $number): array => [
                    'item_number' => $number, 'question' => 'Quiz '.$quiz.' question '.$number.' <script>alert(1)</script>',
                    'correct_count' => $number, 'incorrect_count' => 5 - $number, 'unanswered_count' => 0,
                    'pending_count' => 0, 'response_count' => 5, 'correct_rate' => $number * 20,
                ]);
                $published = new PublishAssessment;
                $published->public_id = '00000000-0000-4000-8000-00000000000'.$quiz;

                return [
                    'assessment' => new Assessment(['title' => 'Quiz '.$quiz, 'reporting_term' => 'midterm']),
                    'publishAssessment' => $published,
                    'class' => new AcademicClass(['year_level' => 4, 'section_name' => 'A']),
                    'report' => new Report(['report_type' => $type, 'report_status' => Report::STATUS_FINALIZED]),
                    'analytics' => [
                        'takers_count' => 5, 'item_count' => 5, 'highest_score' => '5', 'lowest_score' => '1',
                        'mean_score' => '3', 'passing_rate' => 60, 'mean_percentage' => 60,
                        'item_analysis' => $items->all(), 'strongest_items' => [$items->last()], 'weakest_items' => [$items->first()],
                    ],
                ];
            });

            $html = view('instructor.reports.sheet', [
                'reportType' => $type, 'reportTypeLabel' => ucfirst($type), 'rows' => $rows, 'isFinalized' => true,
                'publishAssessmentKeys' => $rows->pluck('publishAssessment.public_id'),
                'reportMeta' => [
                    'semester' => 'Second Semester', 'school_year' => '2026-2027', 'campus' => 'San Carlos',
                    'college' => 'College', 'department' => 'Department', 'course_code_title' => 'TEST / Subject',
                    'students_count' => 5, 'note' => 'Assessment report',
                ],
                'aiCandidates' => [], 'selectedAiProvider' => 'mock', 'errors' => new ViewErrorBag,
            ])->render();

            $this->assertStringContainsString('View Item Analysis', $html);
            $this->assertStringContainsString(ucfirst($type).' Item Analysis', $html);
            $this->assertStringContainsString('data-item-analysis-panel="0"', $html);
            $this->assertMatchesRegularExpression('/data-item-analysis-panel="1"\s+hidden/', $html);
            $this->assertStringContainsString('Quiz 1 question 5 &lt;script&gt;', $html);
            $this->assertStringContainsString('Quiz 2 question 5 &lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringContainsString('Item 5 - 100% correct', $html);
            $this->assertStringContainsString('Item 1 - 20% correct', $html);
        }
    }
}
