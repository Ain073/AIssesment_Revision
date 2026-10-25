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

            $rows->first()['report']->column_headers = ['issues' => 'Saved '.$type.' issue heading'];
            $rows->first()['report']->logo_path = 'report-logos/test.png';

            $reportMeta = [
                'semester' => 'Second Semester', 'school_year' => '2026-2027', 'campus' => 'Alaminos',
                'college' => 'College', 'department' => 'Department', 'course_code_title' => 'TEST / Subject',
                'students_count' => 5, 'note' => 'Assessment report',
                'document_code' => 'FM-AA-CIA-20a', 'document_revision' => 'Rev. 0',
                'document_date' => '30-Oct-2024',
                'prepared_by_name' => 'Example Faculty', 'reviewed_by_name' => 'Example Chair',
            ];
            $html = view('instructor.reports.sheet', [
                'reportType' => $type, 'reportTypeLabel' => ucfirst($type), 'rows' => $rows, 'isFinalized' => true,
                'publishAssessmentKeys' => $rows->pluck('publishAssessment.public_id'),
                'reportMeta' => $reportMeta,
                'errors' => new ViewErrorBag,
            ])->render();

            $this->assertStringContainsString('View Item Analysis', $html);
            $this->assertStringContainsString('Generate AI Draft', $html);
            $this->assertStringNotContainsString('AI Candidate', $html);
            $this->assertStringNotContainsString('id="aiProvider"', $html);
            $this->assertStringContainsString(ucfirst($type).' Item Analysis', $html);
            $this->assertStringContainsString('data-item-analysis-panel="0"', $html);
            $this->assertMatchesRegularExpression('/data-item-analysis-panel="1"\s+hidden/', $html);
            $this->assertStringContainsString('Quiz 1 question 5 &lt;script&gt;', $html);
            $this->assertStringContainsString('Quiz 2 question 5 &lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringContainsString('Item 5 - 100% correct', $html);
            $this->assertStringContainsString('Item 1 - 20% correct', $html);
            $this->assertStringContainsString('Saved '.$type.' issue heading', $html);
            $this->assertStringContainsString('name="campus"', $html);
            $this->assertStringContainsString('data-print-field-name="campus"', $html);
            $this->assertStringContainsString('report-logos/test.png', $html);
            $this->assertStringContainsString('name="document_code"', $html);
            $this->assertStringContainsString('Prepared by:', $html);
            $this->assertStringContainsString('Example Faculty', $html);
            $this->assertStringContainsString('Example Chair', $html);
            $this->assertStringContainsString('<span class="report-print-text">FM-AA-CIA-20a</span>', $html);
            $this->assertSame(1, substr_count($html, 'id="editReportLogoButton"'));
            $this->assertStringContainsString('report-logo-edit d-print-none d-none', $html);
            $this->assertStringContainsString('data-bs-target="#reportLogoModal"', $html);
            $this->assertLessThan(strpos($html, 'id="reportSheetForm"'), strpos($html, 'id="reportLogoForm"'));

            $chairHtml = view('department-chair.reports.show', [
                'reportType' => $type, 'reportTypeLabel' => ucfirst($type), 'report' => $rows->first()['report'],
                'rows' => $rows->take(1), 'reportMeta' => $reportMeta, 'errors' => new ViewErrorBag,
            ])->render();
            $this->assertStringContainsString('Saved '.$type.' issue heading', $chairHtml);
            $this->assertStringContainsString('ALAMINOS', $chairHtml);
            $this->assertStringContainsString('report-logos/test.png', $chairHtml);
            $this->assertStringContainsString('FM-AA-CIA-20a', $chairHtml);
            $this->assertStringContainsString('Example Faculty', $chairHtml);
            $this->assertStringContainsString('Example Chair', $chairHtml);
            $this->assertStringNotContainsString('name="document_code"', $chairHtml);
            $this->assertStringNotContainsString('editReportLogoButton', $chairHtml);
            $this->assertStringNotContainsString('name="column_headers[issues]"', $chairHtml);
        }
    }
}
