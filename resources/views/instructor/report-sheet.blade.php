@extends('layouts.portal')

@section('title', $reportTypeLabel.' Report | AIssessment Instructor')
@section('header', $reportTypeLabel.' Report')
@section('body_class', 'report-focus-mode')

@push('styles')
    <style>
        :root {
            --report-paper-width: 13in;
            --report-paper-height: 8.5in;
            --report-paper-padding: 0.18in;
        }

        body.report-focus-mode {
            overflow-x: auto;
        }

        body.report-focus-mode .sidebar,
        body.report-focus-mode .topbar {
            display: none !important;
        }

        body.report-focus-mode .main-content {
            margin-left: 0;
            padding-top: 0;
            min-height: 100vh;
        }

        body.report-focus-mode .page-container {
            max-width: none;
            width: 100%;
            padding: 0.5rem;
        }

        .report-toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .report-sheet-wrap {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 18px 36px rgba(0, 26, 112, 0.08);
            overflow-x: auto;
            padding: 0.6rem;
        }

        .report-sheet {
            width: max(var(--report-paper-width), calc(100vw - 2.2rem));
            min-width: var(--report-paper-width);
            min-height: var(--report-paper-height);
            margin: 0 auto;
            padding: var(--report-paper-padding);
            color: #1f2937;
            background: #fff;
            border: 1px solid #1f2937;
            box-sizing: border-box;
        }

        .report-header,
        .report-matrix {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-header th,
        .report-header td,
        .report-matrix th,
        .report-matrix td {
            border: 1px solid #374151;
            padding: 0.28rem 0.36rem;
            vertical-align: top;
            font-size: 0.7rem;
            line-height: 1.28;
        }

        .report-header td {
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .report-header th {
            padding-left: 0.45rem;
            padding-right: 0.45rem;
            white-space: nowrap;
        }

        .report-header th,
        .report-matrix th {
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
        }

        .report-logo-cell {
            width: 125px;
            text-align: center;
            vertical-align: middle !important;
        }

        .report-logo {
            width: 62px;
            height: 62px;
            object-fit: contain;
        }

        .report-title {
            font-size: 1.18rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .report-subtitle {
            font-size: 0.66rem;
            font-weight: 500;
        }

        .report-period {
            font-size: 0.8rem !important;
            font-weight: 800;
        }

        .report-note {
            font-style: italic;
            text-align: center;
        }

        .report-matrix {
            margin-top: -1px;
        }

        .report-col-takers { width: 12%; }
        .report-col-items { width: 6%; }
        .report-col-high { width: 6%; }
        .report-col-low { width: 6%; }
        .report-col-mean { width: 9%; }
        .report-col-most { width: 18%; }
        .report-col-least { width: 18%; }
        .report-col-issues { width: 13%; }
        .report-col-action { width: 12%; }

        .report-takers-cell {
            min-height: 135px;
            display: grid;
            place-items: center;
            text-align: center;
            color: #4b5563;
            text-transform: uppercase;
        }

        .report-score-stack {
            display: grid;
            gap: 0.12rem;
            text-align: center;
        }

        .report-score-stack small {
            color: #4b5563;
            font-size: 0.64rem;
        }

        .report-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .report-edit-textarea {
            display: block;
            width: 100%;
            min-height: 8.5rem;
            border: 0;
            resize: none;
            padding: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            line-height: 1.28;
            overflow: hidden;
            overflow-wrap: anywhere;
            scrollbar-width: none;
        }

        .report-edit-textarea::-webkit-scrollbar {
            display: none;
        }

        .report-edit-textarea:focus {
            outline: 2px solid rgba(9, 39, 216, 0.28);
            outline-offset: 3px;
            background: #f8fbff;
        }

        .report-print-text {
            display: none;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        @media print {
            @page {
                size: 13in 8.5in;
                margin: 0.2in;
            }

            body {
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .sidebar,
            .topbar,
            .report-toolbar {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }

            .page-container {
                max-width: none !important;
                padding: 0 !important;
            }

            .report-sheet-wrap {
                border: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
                overflow: visible !important;
            }

            .report-sheet {
                border: 0;
                width: 100%;
                min-width: 100%;
                min-height: auto;
                padding: 0;
            }

            .report-edit-textarea {
                display: none !important;
            }

            .report-print-text {
                display: block !important;
            }
        }
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success report-toolbar">{{ session('status') }}</div>
    @endif

    <div class="report-toolbar">
        <div>
            <h1 class="brand-text mb-1" style="color: var(--psu-navy);">{{ $reportTypeLabel }} Report</h1>
            <p class="text-secondary mb-0">Calculated details are from completed submissions. AI draft is only for most and least learned concepts.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.reports', ['type' => $reportType]) }}">
                <span class="material-symbols-outlined fs-5">arrow_back</span>
                Reports
            </a>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" form="reportSheetForm" type="submit">
                <span class="material-symbols-outlined fs-5">save</span>
                Save Details
            </button>
            <button class="btn btn-psu d-inline-flex align-items-center gap-2" onclick="window.print()" type="button">
                <span class="material-symbols-outlined fs-5">print</span>
                Print
            </button>
        </div>
    </div>

    <form action="{{ route('instructor.reports.save') }}" id="reportSheetForm" method="POST">
        @csrf
        <input name="report_type" type="hidden" value="{{ $reportType }}">
        @foreach ($classAssessmentIds as $classAssessmentId)
            <input name="class_assessment_ids[]" type="hidden" value="{{ $classAssessmentId }}">
        @endforeach

        <div class="report-sheet-wrap">
        <section class="report-sheet">
            <table class="report-header">
                <colgroup>
                    <col style="width: 125px;">
                    <col style="width: 165px;">
                    <col>
                    <col style="width: 170px;">
                    <col style="width: 30%;">
                </colgroup>
                <tr>
                    <td class="report-logo-cell" rowspan="2">
                        <img alt="PSU logo" class="report-logo" src="{{ asset('images/psu-logo-transparent.png') }}">
                    </td>
                    <th colspan="4">
                        <div class="report-title">STUDENTS PERFORMANCE MONITORING ({{ strtoupper($reportTypeLabel) }} ASSESSMENTS)</div>
                        <div class="report-subtitle">PANGASINAN STATE UNIVERSITY</div>
                    </th>
                </tr>
                <tr>
                    <th class="report-period" colspan="4">{{ $reportMeta['semester'] }} AY {{ $reportMeta['school_year'] }}</th>
                </tr>
                <tr>
                    <th>Campus</th>
                    <td colspan="4">{{ strtoupper($reportMeta['campus']) }}</td>
                </tr>
                <tr>
                    <th>College</th>
                    <td colspan="2">{{ strtoupper($reportMeta['college']) }}</td>
                    <th>Course Code/Title</th>
                    <td>{{ $reportMeta['course_code_title'] }}</td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td colspan="2">{{ strtoupper($reportMeta['department']) }}</td>
                    <th>No. of Students</th>
                    <td>{{ $reportMeta['students_count'] }}</td>
                </tr>
                <tr>
                    <td class="report-note" colspan="5">{{ $reportMeta['note'] }}</td>
                </tr>
            </table>

            <table class="report-matrix">
                <colgroup>
                    <col class="report-col-takers">
                    <col class="report-col-items">
                    <col class="report-col-high">
                    <col class="report-col-low">
                    <col class="report-col-mean">
                    <col class="report-col-most">
                    <col class="report-col-least">
                    <col class="report-col-issues">
                    <col class="report-col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th>Total Number of Students Who Took the Assessment</th>
                        <th>Number of Items</th>
                        <th>Highest Score</th>
                        <th>Lowest Score</th>
                        <th>{{ $reportType === 'formative' ? 'Mean Score and Percentage of Students Who Passed' : 'Mean Score' }}</th>
                        <th>Concepts / Skills Most Learned</th>
                        <th>Concepts / Skills Least Learned</th>
                        <th>Issues / Concerns Encountered</th>
                        <th>{{ $reportType === 'formative' ? 'Interventions Done' : 'Future Plans to Improve the Curriculum' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $assessment = $row['assessment'];
                            $analytics = $row['analytics'];
                            $report = $row['report'];
                            $classAssessment = $row['classAssessment'];
                            $rowName = 'reports['.$classAssessment->class_assessment_id.']';
                            $reference = strtoupper(($assessment->reporting_term ?: 'Assessment').' '.$assessment->title);
                            $mostLearned = $report->concept_most_learned_skills ?: $report->ai_most_learned_draft;
                            $leastLearned = $report->concept_least_learned_skills ?: $report->ai_least_learned_draft;
                            $lastColumn = $reportType === 'formative'
                                ? $report->interventions_done
                                : $report->future_plans_curriculum;
                        @endphp
                        <tr>
                            <td>
                                <div class="report-takers-cell">
                                    {{ $reference }}: {{ $analytics['takers_count'] }}
                                </div>
                            </td>
                            <td class="text-center">{{ $analytics['item_count'] }}</td>
                            <td class="text-center">{{ $analytics['highest_score'] }}</td>
                            <td class="text-center">{{ $analytics['lowest_score'] }}</td>
                            <td>
                                <div class="report-score-stack">
                                    <strong>{{ $analytics['mean_score'] }}</strong>
                                    @if ($reportType === 'formative')
                                        <small>{{ $analytics['passing_rate'] }}% passed</small>
                                    @else
                                        <small>{{ $analytics['mean_percentage'] }}%</small>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[concept_most_learned_skills]">{{ old('reports.'.$classAssessment->class_assessment_id.'.concept_most_learned_skills', $mostLearned) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[concept_least_learned_skills]">{{ old('reports.'.$classAssessment->class_assessment_id.'.concept_least_learned_skills', $leastLearned) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[issues_concern]">{{ old('reports.'.$classAssessment->class_assessment_id.'.issues_concern', $report->issues_concern) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                @if ($reportType === 'formative')
                                    <textarea class="report-edit-textarea" name="{{ $rowName }}[interventions_done]">{{ old('reports.'.$classAssessment->class_assessment_id.'.interventions_done', $lastColumn) }}</textarea>
                                @else
                                    <textarea class="report-edit-textarea" name="{{ $rowName }}[future_plans_curriculum]">{{ old('reports.'.$classAssessment->class_assessment_id.'.future_plans_curriculum', $lastColumn) }}</textarea>
                                @endif
                                <div class="report-print-text"></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const syncPrintText = (textarea) => {
                const printText = textarea.nextElementSibling;

                if (printText?.classList.contains('report-print-text')) {
                    printText.textContent = textarea.value;
                }
            };

            const autosize = (textarea) => {
                textarea.style.height = 'auto';
                textarea.style.height = `${Math.max(textarea.scrollHeight, 136)}px`;
                syncPrintText(textarea);
            };

            document.querySelectorAll('.report-edit-textarea').forEach((textarea) => {
                autosize(textarea);
                textarea.addEventListener('input', () => autosize(textarea));
                textarea.addEventListener('change', () => autosize(textarea));
            });

            window.addEventListener('beforeprint', () => {
                document.querySelectorAll('.report-edit-textarea').forEach(autosize);
            });

            window.addEventListener('load', () => {
                document.querySelectorAll('.report-edit-textarea').forEach(autosize);
            });
        })();
    </script>
@endpush
