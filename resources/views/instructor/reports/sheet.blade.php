@extends('layouts.portal')

@section('title', $reportTypeLabel.' Report | AIssessment Instructor')
@section('header', $reportTypeLabel.' Report')
@section('body_class', 'report-focus-mode')

@php
    $paperOptions = config('reports.paper_sizes');
    $reportColumns = array_replace(config('reports.columns'), config('reports.'.$reportType, []));
    $selectedPaper = array_key_exists(request('paper'), $paperOptions) ? request('paper') : 'long';
    $paper = $paperOptions[$selectedPaper];
@endphp

@push('styles')
    <x-page-style path="css/pages/instructor/reports/sheet.css" />
    <style data-page-style>
        :root {
            --report-paper-width: {{ $paper['width'] }};
            --report-paper-height: {{ $paper['height'] }};
            --report-paper-padding: 0.18in;
            --report-preview-ratio: {{ $paper['preview_ratio'] }};
            --report-content-font-family: Arial, Helvetica, sans-serif;
            --report-content-font-size: 9pt;
        }

        @media print {
            @page {
                size: landscape;
                margin: {{ $paper['margin'] }};
            }
        }
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success mb-3 d-print-none" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <div class="report-toolbar">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="brand-text mb-0" style="color: var(--psu-navy);">{{ $reportTypeLabel }} Report</h1>
                <span class="report-status-badge badge {{ $isFinalized ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' }} d-inline-flex align-items-center gap-1 px-2.5 py-1 fs-7 fw-bold" id="reportStatusBadge">
                    <span class="material-symbols-outlined fs-6" id="reportStatusBadgeIcon">{{ $isFinalized ? 'lock' : 'edit_document' }}</span>
                    <span id="reportStatusBadgeText">{{ $isFinalized ? 'Finalized' : 'Draft' }}</span>
                </span>
            </div>
            <p class="text-secondary mb-0">Calculated details are from completed submissions. AI draft is only for most and least learned concepts.</p>
        </div>
        <div class="report-toolbar-actions">
            <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.reports', ['type' => $reportType]) }}">
                <span class="material-symbols-outlined fs-5">arrow_back</span>
                Back
            </a>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="modal" data-bs-target="#reportItemAnalysisModal">
                <span class="material-symbols-outlined fs-5" aria-hidden="true">analytics</span>
                View Item Analysis
            </button>
            {{-- Paper Size Control (commented out - paper size is set directly in the print dialog; defaults to Long / Legal)
            <div class="report-paper-control">
                <label for="paperSize">Paper</label>
                <select class="form-select form-select-sm" id="paperSize">
                    @foreach ($paperOptions as $key => $option)
                        <option value="{{ $key }}" @selected($selectedPaper === $key)>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <span class="report-paper-caption" id="paperSizeCaption">{{ $paper['description'] }}</span>
            </div>
            --}}
            <div class="report-paper-control report-font-family-control">
                <label for="reportContentFontFamily">Font</label>
                <select class="form-select form-select-sm" id="reportContentFontFamily">
                    <option value="arial">Arial</option>
                    <option value="calibri">Calibri</option>
                    <option value="times">Times New Roman</option>
                </select>
            </div>
            <div class="report-paper-control report-font-size-control">
                <label for="reportContentFontSize">Size</label>
                <select class="form-select form-select-sm" id="reportContentFontSize">
                    @foreach ([8, 9, 10, 11, 12] as $fontSize)
                        <option value="{{ $fontSize }}" @selected($fontSize === 9)>{{ $fontSize }}</option>
                    @endforeach
                </select>
            </div>
            <div class="report-paper-control {{ $isFinalized ? 'd-none' : '' }}" id="aiModelLabel">
                <span class="small fw-bold text-secondary">AI Model</span>
                <strong class="text-nowrap">{{ config('services.ai_report.providers.openai.label', 'GPT-4.1') }}</strong>
            </div>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2 {{ $isFinalized ? 'd-none' : '' }}" id="aiDraftButton" type="button" data-bs-toggle="modal" data-bs-target="#confirmAiDraftModal">
                <span class="material-symbols-outlined fs-5">auto_awesome</span>
                Generate AI Draft
            </button>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2 {{ $isFinalized ? 'd-none' : '' }}" id="saveDraftButton" form="reportSheetForm" name="save_action" value="draft" type="submit">
                <span class="material-symbols-outlined fs-5">save</span>
                Save Draft
            </button>
            <button class="btn btn-psu d-inline-flex align-items-center gap-2 {{ $isFinalized ? 'd-none' : '' }}" id="finalizeButton" form="reportSheetForm" name="save_action" value="finalized" type="submit">
                <span class="material-symbols-outlined fs-5">task_alt</span>
                Finalize
            </button>
            <button class="btn btn-primary d-inline-flex align-items-center gap-2 {{ $isFinalized ? '' : 'd-none' }}" id="editReportButton" type="button">
                <span class="material-symbols-outlined fs-5">edit</span>
                Edit
            </button>
            <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 d-none" id="cancelEditButton" type="button">
                <span class="material-symbols-outlined fs-5">close</span>
                Cancel
            </button>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" onclick="window.print()" type="button">
                <span class="material-symbols-outlined fs-5">print</span>
                Export
            </button>
        </div>
    </div>

    <div class="alert alert-light border d-flex align-items-center justify-content-between p-2.5 mb-3 d-print-none {{ $isFinalized ? '' : 'd-none' }}" id="reportLockedNotice">
        <div class="d-flex align-items-center gap-2 small text-secondary">
            <span class="material-symbols-outlined text-success fs-5">verified</span>
            <span>This report is <strong>finalized</strong> and locked. Click <strong>Edit</strong> if you need to modify it.</span>
        </div>
    </div>

    <div class="alert report-ai-status" id="reportAiStatus"></div>

    <div class="modal fade" id="confirmAiDraftModal" tabindex="-1" aria-labelledby="confirmAiDraftModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="confirmAiDraftModalLabel">Generate AI Draft?</h2>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Generate an AI draft using <strong>{{ config('services.ai_report.providers.openai.label', 'GPT-4.1') }}</strong> for the selected reports?
                    </p>
                    <p class="text-secondary mb-0">
                        This fills the most and least learned concepts. Review and edit the generated text before finalizing your report.
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

    @include('instructor.reports.item-analysis')

    <div class="d-print-none mb-3 d-flex align-items-center gap-2" id="reportAutosaveFeedback">
        <span role="status" aria-live="polite" data-report-autosave-status></span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-report-autosave-retry hidden>Retry save</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-report-backup-download hidden>Download unsaved copy</button>
    </div>
    <form action="{{ route('instructor.reports.save') }}" id="reportSheetForm" method="POST"
          data-report-autosave-url="{{ route('instructor.reports.autosave') }}"
          data-report-account="{{ auth()->id() }}"
          data-report-unsaved="{{ $errors->any() ? 'true' : 'false' }}"
          data-report-autosave-enabled="{{ $rows->contains(fn ($row) => $row['report']?->report_status === \App\Models\Report::STATUS_FINALIZED) ? 'false' : 'true' }}"
          data-report-conflict="{{ $errors->has('report_conflict') ? 'true' : 'false' }}">
        @csrf
        @foreach ($rows as $row)
            <input type="hidden" name="revisions[{{ $row['publishAssessment']->public_id }}]" data-report-revision="{{ $row['publishAssessment']->public_id }}" value="{{ old('revisions.'.$row['publishAssessment']->public_id, (int) $row['report']?->revision) }}">
        @endforeach
        <input name="report_type" type="hidden" value="{{ $reportType }}">
        <input id="paperSizeInput" name="paper_size" type="hidden" value="{{ $selectedPaper }}">
        @foreach ($publishAssessmentKeys as $publishAssessmentKey)
            <input name="publish_assessment_keys[]" type="hidden" value="{{ $publishAssessmentKey }}">
        @endforeach

        <div class="report-sheet-wrap {{ $isFinalized ? 'is-locked' : '' }}">
        <section class="report-sheet">
            <table class="report-header">
                @include('partials.reports.column-widths')
                <tbody>
                    @include('partials.reports.header-rows', ['courseFieldMode' => 'input'])

                </tbody>
            </table>

            <table class="report-matrix">
                @include('partials.reports.column-widths')
                <thead>
                    @include('partials.reports.column-headings')
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $assessment = $row['assessment'];
                            $analytics = $row['analytics'];
                            $report = $row['report'];
                            $publishAssessment = $row['publishAssessment'];
                            $publishAssessmentKey = $publishAssessment->public_id;
                            $rowName = 'reports['.$publishAssessmentKey.']';
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
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[concept_most_learned_skills]" data-assessment-key="{{ $publishAssessmentKey }}" data-ai-field="concepts_most_learned_skills" @if ($isFinalized) readonly tabindex="-1" @endif>{{ old('reports.'.$publishAssessmentKey.'.concept_most_learned_skills', $mostLearned) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[concept_least_learned_skills]" data-assessment-key="{{ $publishAssessmentKey }}" data-ai-field="concepts_least_learned_skills" @if ($isFinalized) readonly tabindex="-1" @endif>{{ old('reports.'.$publishAssessmentKey.'.concept_least_learned_skills', $leastLearned) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                <textarea class="report-edit-textarea" name="{{ $rowName }}[issues_concern]" @if ($isFinalized) readonly tabindex="-1" @endif>{{ old('reports.'.$publishAssessmentKey.'.issues_concern', $report->issues_concern) }}</textarea>
                                <div class="report-print-text"></div>
                            </td>
                            <td>
                                @if ($reportType === 'formative')
                                    <textarea class="report-edit-textarea" name="{{ $rowName }}[interventions_done]" @if ($isFinalized) readonly tabindex="-1" @endif>{{ old('reports.'.$publishAssessmentKey.'.interventions_done', $lastColumn) }}</textarea>
                                @else
                                    <textarea class="report-edit-textarea" name="{{ $rowName }}[future_plans_curriculum]" @if ($isFinalized) readonly tabindex="-1" @endif>{{ old('reports.'.$publishAssessmentKey.'.future_plans_curriculum', $lastColumn) }}</textarea>
                                @endif
                                <div class="report-print-text"></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="report-print-table">
                @include('partials.reports.column-widths')
                <tbody>
                    @include('partials.reports.header-rows', ['courseFieldMode' => 'mirror'])

                    @include('partials.reports.column-headings')
                    @foreach ($rows as $row)
                        @php
                            $assessment = $row['assessment'];
                            $analytics = $row['analytics'];
                            $report = $row['report'];
                            $publishAssessment = $row['publishAssessment'];
                            $publishAssessmentKey = $publishAssessment->public_id;
                            $rowName = 'reports['.$publishAssessmentKey.']';
                            $reference = strtoupper(($assessment->reporting_term ?: 'Assessment').' '.$assessment->title);
                            $mostLearned = old('reports.'.$publishAssessmentKey.'.concept_most_learned_skills', $report->concept_most_learned_skills);
                            $leastLearned = old('reports.'.$publishAssessmentKey.'.concept_least_learned_skills', $report->concept_least_learned_skills);
                            $issuesConcern = old('reports.'.$publishAssessmentKey.'.issues_concern', $report->issues_concern);
                            $lastColumn = $reportType === 'formative'
                                ? old('reports.'.$publishAssessmentKey.'.interventions_done', $report->interventions_done)
                                : old('reports.'.$publishAssessmentKey.'.future_plans_curriculum', $report->future_plans_curriculum);
                            $lastColumnField = $reportType === 'formative' ? 'interventions_done' : 'future_plans_curriculum';
                        @endphp
                        <tr>
                            <td>
                                <div class="report-print-takers-cell">
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
                                <div class="report-print-data-cell report-print-mirror" data-print-field-name="{{ $rowName }}[concept_most_learned_skills]">{{ $mostLearned }}</div>
                            </td>
                            <td>
                                <div class="report-print-data-cell report-print-mirror" data-print-field-name="{{ $rowName }}[concept_least_learned_skills]">{{ $leastLearned }}</div>
                            </td>
                            <td>
                                <div class="report-print-data-cell report-print-mirror" data-print-field-name="{{ $rowName }}[issues_concern]">{{ $issuesConcern }}</div>
                            </td>
                            <td>
                                <div class="report-print-data-cell report-print-mirror" data-print-field-name="{{ $rowName }}[{{ $lastColumnField }}]">{{ $lastColumn }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="report-print-grid-sheet" aria-hidden="true">
                <div class="report-print-grid">
                    <div class="report-print-cell report-print-logo-cell" style="grid-column: 1 / 2; grid-row: 1 / span 2;">
                        <img alt="PSU logo" class="report-logo" src="{{ asset('images/psu-logo-transparent.png') }}">
                    </div>
                    <div class="report-print-cell report-print-title-cell" style="grid-column: 2 / 10; grid-row: 1;">
                        <div class="report-title">STUDENTS PERFORMANCE MONITORING ({{ strtoupper($reportTypeLabel) }} ASSESSMENTS)</div>
                        <div class="report-subtitle">PANGASINAN STATE UNIVERSITY</div>
                    </div>
                    <div class="report-print-cell report-print-period-cell" style="grid-column: 2 / 10; grid-row: 2;">
                        <div class="report-period">{{ $reportMeta['semester'] }} AY {{ $reportMeta['school_year'] }}</div>
                    </div>

                    <div class="report-print-cell report-print-label" style="grid-column: 1 / 2;">Campus</div>
                    <div class="report-print-cell" style="grid-column: 2 / 10;">{{ strtoupper($reportMeta['campus']) }}</div>

                    <div class="report-print-cell report-print-label" style="grid-column: 1 / 2;">College</div>
                    <div class="report-print-cell" style="grid-column: 2 / 6;">{{ strtoupper($reportMeta['college']) }}</div>
                    <div class="report-print-cell report-print-label" style="grid-column: 6 / 7;">Course Code/Title</div>
                    <div class="report-print-cell" style="grid-column: 7 / 10;">
                        <span class="report-print-mirror" data-print-field-name="course_code_title">{{ $reportMeta['course_code_title'] }}</span>
                    </div>

                    <div class="report-print-cell report-print-label" style="grid-column: 1 / 2;">Department</div>
                    <div class="report-print-cell" style="grid-column: 2 / 6;">{{ strtoupper($reportMeta['department']) }}</div>
                    <div class="report-print-cell report-print-label" style="grid-column: 6 / 7;">No. of Students</div>
                    <div class="report-print-cell" style="grid-column: 7 / 10;">{{ $reportMeta['students_count'] }}</div>

                    <div class="report-print-cell report-print-note-cell" style="grid-column: 1 / 10;">{{ $reportMeta['note'] }}</div>

                    <div class="report-print-cell report-print-column">{{ $reportColumns['takers'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['items'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['high'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['low'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['mean'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['most'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['least'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['issues'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['action'] }}</div>

                    @foreach ($rows as $row)
                        @php
                            $assessment = $row['assessment'];
                            $analytics = $row['analytics'];
                            $report = $row['report'];
                            $publishAssessment = $row['publishAssessment'];
                            $publishAssessmentKey = $publishAssessment->public_id;
                            $rowName = 'reports['.$publishAssessmentKey.']';
                            $reference = strtoupper(($assessment->reporting_term ?: 'Assessment').' '.$assessment->title);
                            $mostLearned = old('reports.'.$publishAssessmentKey.'.concept_most_learned_skills', $report->concept_most_learned_skills);
                            $leastLearned = old('reports.'.$publishAssessmentKey.'.concept_least_learned_skills', $report->concept_least_learned_skills);
                            $issuesConcern = old('reports.'.$publishAssessmentKey.'.issues_concern', $report->issues_concern);
                            $lastColumn = $reportType === 'formative'
                                ? old('reports.'.$publishAssessmentKey.'.interventions_done', $report->interventions_done)
                                : old('reports.'.$publishAssessmentKey.'.future_plans_curriculum', $report->future_plans_curriculum);
                            $lastColumnField = $reportType === 'formative' ? 'interventions_done' : 'future_plans_curriculum';
                        @endphp
                        <div class="report-print-data-row">
                            <div class="report-print-cell">
                                <div class="report-print-row-title">{{ $reference }}: {{ $analytics['takers_count'] }}</div>
                            </div>
                            <div class="report-print-cell text-center">{{ $analytics['item_count'] }}</div>
                            <div class="report-print-cell text-center">{{ $analytics['highest_score'] }}</div>
                            <div class="report-print-cell text-center">{{ $analytics['lowest_score'] }}</div>
                            <div class="report-print-cell">
                                <div class="report-score-stack">
                                    <strong>{{ $analytics['mean_score'] }}</strong>
                                    @if ($reportType === 'formative')
                                        <small>{{ $analytics['passing_rate'] }}% passed</small>
                                    @else
                                        <small>{{ $analytics['mean_percentage'] }}%</small>
                                    @endif
                                </div>
                            </div>
                            <div class="report-print-cell">
                                <div class="report-print-grid-text report-print-mirror" data-print-field-name="{{ $rowName }}[concept_most_learned_skills]">{{ $mostLearned }}</div>
                            </div>
                            <div class="report-print-cell">
                                <div class="report-print-grid-text report-print-mirror" data-print-field-name="{{ $rowName }}[concept_least_learned_skills]">{{ $leastLearned }}</div>
                            </div>
                            <div class="report-print-cell">
                                <div class="report-print-grid-text report-print-mirror" data-print-field-name="{{ $rowName }}[issues_concern]">{{ $issuesConcern }}</div>
                            </div>
                            <div class="report-print-cell">
                                <div class="report-print-grid-text report-print-mirror" data-print-field-name="{{ $rowName }}[{{ $lastColumnField }}]">{{ $lastColumn }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        </div>
    </form>
@endsection

@push('scripts')
    @include('instructor.reports.report-sheet-page-code')
@endpush
