@extends('layouts.portal')

@section('title', 'Reports | AIssessment Instructor')

@section('topbar-leading')
    @include('partials.reports-view-selector')
@endsection

@php
    $activeReportType = request()->query('type') === 'summative' ? 'summative' : 'formative';
    $reportGroups = [
        'formative' => [
            'label' => 'Formative Reports',
            'icon' => 'description',
            'items' => $formativeAssessments,
        ],
        'summative' => [
            'label' => 'Summative Reports',
            'icon' => 'assignment',
            'items' => $summativeAssessments,
        ],
    ];
@endphp

@push('styles')
    <x-page-style path="css/pages/instructor/reports/index.css" />
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="brand-text mb-1" style="color: var(--psu-navy);">Reports</h1>
        </div>
        <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments', ['tab' => 'published']) }}">
            <span class="material-symbols-outlined fs-5">assignment</span>
            Published Assessments
        </a>
    </div>

    <section class="mb-4" aria-label="Report filters">
        <form action="{{ route('instructor.reports') }}" class="record-filters" method="GET" data-ajax-page-form data-preserve-page-state="true">
            <input type="hidden" name="search" value="{{ \App\Support\RecordSearch::term() }}">
            <input data-report-type-input name="type" type="hidden" value="{{ $activeReportType }}">

            <div class="record-filter-field">
                <label class="record-filter-label" for="instructorReportClassFilter">Class</label>
                <select class="form-select" id="instructorReportClassFilter" name="class" data-submit-on-change @disabled($classes->isEmpty())>
                    <option value="">All classes</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->class_id }}" @selected($selectedClassId === (int) $class->class_id)>
                            {{ $class->displayName() }}
                            @if ($class->subject?->subject_code)
                                - {{ $class->subject->subject_code }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($selectedClassId)
                <div class="record-filter-actions">
                    <a
                        aria-label="Clear report filters"
                        class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                        href="{{ route('instructor.reports', ['type' => $activeReportType]) }}"
                        title="Clear filters"
                    >
                        <span class="material-symbols-outlined fs-6">filter_alt_off</span>
                    </a>
                </div>
            @endif
        </form>
    </section>

    <div class="table-switch-tabs">
        @foreach ($reportGroups as $type => $group)
            <button
                class="btn btn-outline-primary table-switch-button {{ $activeReportType === $type ? 'active' : '' }} d-inline-flex align-items-center gap-2"
                data-report-tab="{{ $type }}"
                type="button"
            >
                <span class="material-symbols-outlined fs-5">{{ $group['icon'] }}</span>
                {{ $group['label'] }}
                <span class="table-switch-count">{{ $group['items']->count() }}</span>
            </button>
        @endforeach
    </div>

    @foreach ($reportGroups as $type => $group)
        <section class="reports-panel mb-4" data-report-panel="{{ $type }}" @if ($activeReportType !== $type) hidden @endif>
            <div class="reports-panel-header">
                <div>
                    <h2 class="h4 mb-1">{{ $group['label'] }}</h2>
                </div>
                <span class="badge text-bg-light border">{{ $group['items']->count() }} completed</span>
            </div>

            <form action="{{ route('instructor.reports.prepare') }}" class="p-3 p-lg-4" method="POST" data-report-select-form="{{ $type }}">
                @csrf
                <input type="hidden" name="report_type" value="{{ $type }}">

                @if ($group['items']->isNotEmpty())
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <label class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2 mb-0">
                            <input class="form-check-input mt-0" type="checkbox" data-report-select-all="{{ $type }}">
                            Select All
                        </label>
                        <span class="small text-secondary" data-report-selected-count="{{ $type }}">0 selected</span>
                    </div>

                    <div class="d-grid gap-3">
                        @foreach ($group['items'] as $publishAssessment)
                            @php
                                $assessment = $publishAssessment->assessment;
                                $class = $publishAssessment->class;
                                $report = $publishAssessment->report;
                            @endphp
                            <label class="report-assessment-card mb-0">
                                <input
                                    class="form-check-input report-check mt-1"
                                    name="publish_assessment_keys[]"
                                    type="checkbox"
                                    value="{{ $publishAssessment->public_id }}"
                                    data-report-checkbox="{{ $type }}"
                                >
                                <span>
                                    <span class="fw-bold d-block" style="color: var(--psu-navy);">{{ $assessment->title }}</span>
                                    <span class="report-meta-line d-block">
                                        {{ $assessment->subject?->subject_code ?? 'No subject' }}
                                        @if ($class)
                                            | {{ $class->class_name }}
                                        @endif
                                        @if ($class?->school_year)
                                            | {{ $class->school_year }}
                                        @endif
                                    </span>
                                    <span class="report-meta-line d-block">
                                        Due:
                                        {{ $publishAssessment->due_at ? $publishAssessment->due_at->format('M d, Y h:i A') : 'No due date' }}
                                        | Submissions: {{ $publishAssessment->submissions_count }}
                                        | Term: {{ ucfirst((string) $assessment->reporting_term) }}
                                    </span>
                                </span>
                                @if ($report)
                                    <span class="report-badge {{ $report->report_status === \App\Models\Report::STATUS_FINALIZED ? 'finalized' : 'draft' }}">
                                        {{ ucfirst($report->report_status) }}
                                    </span>
                                @else
                                    <span class="report-badge none">Not Generated</span>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button class="btn btn-psu d-inline-flex align-items-center gap-2" type="submit" data-report-submit="{{ $type }}" disabled>
                            <span class="material-symbols-outlined fs-5">summarize</span>
                            Generate Report
                        </button>
                    </div>
                @else
                    <div class="report-empty">
                        No completed {{ strtolower($reportCategories[$type]) }} assessments yet.
                    </div>
                @endif
            </form>
        </section>
    @endforeach
@endsection
