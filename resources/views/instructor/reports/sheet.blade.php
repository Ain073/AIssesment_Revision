@extends('layouts.portal')

@section('title', $reportTypeLabel.' Report | AIssessment Instructor')
@section('header', $reportTypeLabel.' Report')
@section('body_class', 'report-focus-mode')

@php
    $paperOptions = [
        'a4' => [
            'label' => 'A4',
            'description' => '11.69 x 8.27 in',
            'width' => '11.69in',
            'height' => '8.27in',
            'margin' => '0.18in',
            'preview_ratio' => '0.9',
        ],
        'short' => [
            'label' => 'Short / Letter',
            'description' => '11 x 8.5 in',
            'width' => '11in',
            'height' => '8.5in',
            'margin' => '0.18in',
            'preview_ratio' => '0.846',
        ],
        'long' => [
            'label' => 'Long / Legal',
            'description' => '13 x 8.5 in',
            'width' => '13in',
            'height' => '8.5in',
            'margin' => '0.2in',
            'preview_ratio' => '1',
        ],
    ];
    $selectedPaper = array_key_exists(request('paper'), $paperOptions) ? request('paper') : 'long';
    $paper = $paperOptions[$selectedPaper];
@endphp

@push('styles')
    <style>
        :root {
            --report-paper-width: {{ $paper['width'] }};
            --report-paper-height: {{ $paper['height'] }};
            --report-paper-padding: 0.18in;
            --report-preview-ratio: {{ $paper['preview_ratio'] }};
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

        .report-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .report-paper-control {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            min-height: 40px;
            padding: 0.35rem 0.6rem;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
        }

        .report-paper-control label {
            font-size: 0.74rem;
            font-weight: 800;
            color: #4b5563;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-paper-control select {
            width: auto;
            min-width: 140px;
            border: 0;
            padding: 0;
            color: var(--psu-navy);
            font-weight: 700;
            background-color: transparent;
            box-shadow: none;
        }

        .report-paper-caption {
            min-width: 86px;
            font-size: 0.72rem;
            color: #6b7280;
            white-space: nowrap;
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
            width: clamp(
                var(--report-paper-width),
                calc((100vw - 2.2rem) * var(--report-preview-ratio)),
                calc(100vw - 2.2rem)
            );
            min-width: var(--report-paper-width);
            min-height: var(--report-paper-height);
            margin: 0 auto;
            padding: var(--report-paper-padding);
            color: #1f2937;
            background: #fff;
            border: 2px solid #111827;
            box-sizing: border-box;
        }

        .report-table,
        .report-header,
        .report-matrix {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td,
        .report-header th,
        .report-header td,
        .report-matrix th,
        .report-matrix td {
            border: 1px solid #111827;
            padding: 0.28rem 0.36rem;
            vertical-align: top;
            font-size: 0.7rem;
            line-height: 1.28;
        }

        .report-table tr:first-child th,
        .report-table tr:first-child td,
        .report-header tr:first-child th,
        .report-header tr:first-child td {
            border-top-width: 2px;
            border-bottom-width: 2px;
        }

        .report-table tr:nth-child(2) th,
        .report-table tr:nth-child(2) td,
        .report-header tr:nth-child(2) th,
        .report-header tr:nth-child(2) td {
            border-bottom-width: 2px;
        }

        .report-table tr:nth-child(5) th,
        .report-table tr:nth-child(5) td,
        .report-header tr:nth-child(5) th,
        .report-header tr:nth-child(5) td {
            border-bottom-width: 2px;
        }

        .report-table td,
        .report-header td {
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .report-header-input {
            width: 100%;
            border: 0;
            padding: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            line-height: inherit;
        }

        .report-header-input:focus {
            outline: 2px solid rgba(9, 39, 216, 0.28);
            outline-offset: 2px;
            background: #f8fbff;
        }

        .report-table tr:nth-child(-n + 5) th,
        .report-header th {
            padding-left: 0.45rem;
            padding-right: 0.45rem;
            white-space: nowrap;
        }

        .report-table th,
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
            border-top: 2px solid #111827 !important;
            border-bottom: 3px double #111827 !important;
        }

        .report-matrix {
            margin-top: -3px;
            border-top: 0;
            border-bottom: 2px solid #111827;
        }

        .report-matrix thead th {
            border-top: 0;
            border-bottom: 2px solid #111827;
        }

        .report-matrix tbody tr:last-child td {
            border-bottom-width: 2px;
        }

        .report-matrix-head th {
            border-top: 0;
            border-bottom: 2px solid #111827;
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

        .report-ai-status {
            display: none;
            margin-bottom: 1rem;
        }

        .report-ai-status.show {
            display: block;
        }

        @media print {
            @page {
                size: {{ $paper['width'] }} {{ $paper['height'] }};
                margin: {{ $paper['margin'] }};
            }

            .report-ai-status,
            #reportAiStatus {
                display: none !important;
            }

            body {
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body::before,
            body::after {
                content: "";
                position: fixed;
                left: 0;
                right: 0;
                z-index: 9999;
                pointer-events: none;
            }

            body::before {
                top: 0;
                border-top: 2px solid #111827;
            }

            body::after {
                bottom: 0;
                border-bottom: 2px solid #111827;
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
                height: auto !important;
                min-height: 0 !important;
                padding: 0;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .report-table {
                border-collapse: separate !important;
                border-spacing: 0 !important;
                -webkit-box-decoration-break: clone;
                box-decoration-break: clone;
            }

            .report-table th,
            .report-table td {
                border: 0 !important;
                border-right: 1px solid #111827 !important;
                border-bottom: 1px solid #111827 !important;
                -webkit-box-decoration-break: clone;
                box-decoration-break: clone;
            }

            .report-table tr > :first-child {
                border-left: 1px solid #111827 !important;
            }

            .report-table tr:first-child > * {
                border-top: 2px solid #111827 !important;
                border-top-width: 2px !important;
                border-bottom-width: 2px !important;
            }

            .report-table tr:nth-child(2) > *,
            .report-table tr:nth-child(5) > *,
            .report-matrix-head > * {
                border-bottom-width: 2px !important;
            }

            .report-note {
                border-top-width: 2px !important;
                border-bottom: 3px double #111827 !important;
            }

            .report-table tr:last-child > * {
                border-bottom-width: 2px !important;
            }

            .report-table {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .report-table tr,
            .report-table td,
            .report-table th {
                break-inside: auto;
                page-break-inside: auto;
            }

            .report-takers-cell {
                min-height: 0.85in;
            }

            .report-edit-textarea {
                display: none !important;
            }

            .report-print-text {
                display: block !important;
            }

            .report-header-input {
                outline: 0 !important;
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
        <div class="report-toolbar-actions">
            <div class="report-paper-control">
                <label for="paperSize">Paper</label>
                <select class="form-select form-select-sm" id="paperSize">
                    @foreach ($paperOptions as $key => $option)
                        <option value="{{ $key }}" @selected($selectedPaper === $key)>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <span class="report-paper-caption" id="paperSizeCaption">{{ $paper['description'] }}</span>
            </div>
            <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.reports', ['type' => $reportType]) }}">
                <span class="material-symbols-outlined fs-5">arrow_back</span>
                Reports
            </a>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" form="reportSheetForm" name="save_action" value="draft" type="submit">
                <span class="material-symbols-outlined fs-5">save</span>
                Save Draft
            </button>
            <button class="btn btn-psu d-inline-flex align-items-center gap-2" form="reportSheetForm" name="save_action" value="finalized" type="submit">
                <span class="material-symbols-outlined fs-5">task_alt</span>
                Finalize
            </button>
            <div class="report-paper-control">
                <label for="aiProvider">AI Candidate</label>
                <select class="form-select form-select-sm" id="aiProvider">
                    @foreach ($aiCandidates as $candidate)
                        <option value="{{ $candidate['value'] }}" @selected($selectedAiProvider === $candidate['value'])>{{ $candidate['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="modal" data-bs-target="#confirmAiDraftModal">
                <span class="material-symbols-outlined fs-5">auto_awesome</span>
                AI Draft
            </button>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" onclick="window.print()" type="button">
                <span class="material-symbols-outlined fs-5">print</span>
                Print
            </button>
        </div>
    </div>

    <div class="alert report-ai-status" id="reportAiStatus"></div>

    <div class="modal fade" id="confirmAiDraftModal" tabindex="-1" aria-labelledby="confirmAiDraftModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="confirmAiDraftModalLabel">Generate AI Draft?</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        This will call <strong id="confirmAiProviderLabel">the selected AI candidate</strong> and may consume API credits.
                    </p>
                    <p class="text-secondary mb-0">
                        Continue only when you are ready to generate report content for the selected assessment results.
                    </p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-psu d-inline-flex align-items-center gap-2" id="generateAiDraftsButton" type="button" data-ai-url="{{ route('instructor.reports.ai-drafts') }}">
                        <span class="material-symbols-outlined fs-5">auto_awesome</span>
                        Generate AI Draft
                    </button>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('instructor.reports.save') }}" id="reportSheetForm" method="POST">
        @csrf
        <input name="report_type" type="hidden" value="{{ $reportType }}">
        <input id="paperSizeInput" name="paper_size" type="hidden" value="{{ $selectedPaper }}">
        @foreach ($classAssessmentKeys as $classAssessmentKey)
            <input name="class_assessment_keys[]" type="hidden" value="{{ $classAssessmentKey }}">
        @endforeach

        <div class="report-sheet-wrap">
        <section class="report-sheet">
            <table class="report-table">
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
                <tbody>
                    <tr>
                        <td class="report-logo-cell" rowspan="2">
                            <img alt="PSU logo" class="report-logo" src="{{ asset('images/psu-logo-transparent.png') }}">
                        </td>
                        <th colspan="8">
                            <div class="report-title">STUDENTS PERFORMANCE MONITORING ({{ strtoupper($reportTypeLabel) }} ASSESSMENTS)</div>
                            <div class="report-subtitle">PANGASINAN STATE UNIVERSITY</div>
                        </th>
                    </tr>
                    <tr>
                        <th class="report-period" colspan="8">{{ $reportMeta['semester'] }} AY {{ $reportMeta['school_year'] }}</th>
                    </tr>
                    <tr>
                        <th>Campus</th>
                        <td colspan="8">{{ strtoupper($reportMeta['campus']) }}</td>
                    </tr>
                    <tr>
                        <th>College</th>
                        <td colspan="4">{{ strtoupper($reportMeta['college']) }}</td>
                        <th colspan="2">Course Code/Title</th>
                        <td colspan="2">
                            <input class="report-header-input" name="course_code_title" type="text" value="{{ old('course_code_title', $reportMeta['course_code_title']) }}">
                        </td>
                    </tr>
                    <tr>
                        <th>Department</th>
                        <td colspan="4">{{ strtoupper($reportMeta['department']) }}</td>
                        <th colspan="2">No. of Students</th>
                        <td colspan="2">{{ $reportMeta['students_count'] }}</td>
                    </tr>
                    <tr>
                        <td class="report-note" colspan="9">{{ $reportMeta['note'] }}</td>
                    </tr>
                    <tr class="report-matrix-head">
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
                    @foreach ($rows as $row)
                        @php
                            $assessment = $row['assessment'];
                            $analytics = $row['analytics'];
                            $report = $row['report'];
                            $classAssessment = $row['classAssessment'];
                            $classAssessmentKey = $classAssessment->public_id;
                            $rowName = 'reports['.$classAssessmentKey.']';
                            $reference = strtoupper(($assessment->reporting_term ?: 'Assessment').' '.$assessment->title);
                            $mostLearned = $report->concept_most_learned_skills;
                            $leastLearned = $report->concept_least_learned_skills;
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
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[concept_most_learned_skills]" data-assessment-key="{{ $classAssessmentKey }}" data-ai-field="concepts_most_learned_skills">{{ old('reports.'.$classAssessmentKey.'.concept_most_learned_skills', $mostLearned) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[concept_least_learned_skills]" data-assessment-key="{{ $classAssessmentKey }}" data-ai-field="concepts_least_learned_skills">{{ old('reports.'.$classAssessmentKey.'.concept_least_learned_skills', $leastLearned) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[issues_concern]">{{ old('reports.'.$classAssessmentKey.'.issues_concern', $report->issues_concern) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                @if ($reportType === 'formative')
                                    <textarea class="report-edit-textarea" name="{{ $rowName }}[interventions_done]">{{ old('reports.'.$classAssessmentKey.'.interventions_done', $lastColumn) }}</textarea>
                                @else
                                    <textarea class="report-edit-textarea" name="{{ $rowName }}[future_plans_curriculum]">{{ old('reports.'.$classAssessmentKey.'.future_plans_curriculum', $lastColumn) }}</textarea>
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
    @include('instructor.reports.report-sheet-page-code')
@endpush
