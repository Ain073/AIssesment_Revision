@extends('layouts.portal')

@section('title', 'Reports | AIssessment Department Chair')

@section('topbar-leading')
    @include('partials.reports-view-selector')
@endsection

@php
    $activeReportType = request()->query('type') === 'summative' ? 'summative' : 'formative';
    $reportGroups = [
        'formative' => [
            'label' => 'Formative Reports',
            'icon' => 'description',
            'items' => $formativeReports,
        ],
        'summative' => [
            'label' => 'Summative Reports',
            'icon' => 'assignment',
            'items' => $summativeReports,
        ],
    ];
@endphp

@push('styles')
    <x-page-style path="css/pages/department-chair/reports/index.css" />
@endpush

@section('content')
    <section class="reports-hero">
        <p class="small fw-bold text-uppercase mb-2" style="color: rgba(255, 245, 191, 0.92);">Department Chair Module</p>
        <h2 class="brand-text h1 mb-3">Finalized Reports</h2>
    </section>

    <section class="my-4" aria-label="Report filters">
        <form action="{{ route('department-chair.reports') }}" class="record-filters" method="GET" data-ajax-page-form data-preserve-page-state="true">
            <input type="hidden" name="search" value="{{ \App\Support\RecordSearch::term() }}">
            <input data-report-type-input name="type" type="hidden" value="{{ $activeReportType }}">

            <div class="record-filter-field">
                <label class="record-filter-label" for="reportTeacherFilter">Teacher</label>
                <select class="form-select" id="reportTeacherFilter" name="teacher" data-submit-on-change @disabled($teachers->isEmpty())>
                    <option value="">All teachers</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->instructor_profile_id }}" @selected($selectedTeacherId === $teacher->instructor_profile_id)>
                            {{ $teacher->user?->displayName() ?? 'Unnamed teacher' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="record-filter-field">
                <label class="record-filter-label" for="reportSubjectFilter">Subject</label>
                <select class="form-select" id="reportSubjectFilter" name="subject" data-submit-on-change @disabled($subjects->isEmpty())>
                    <option value="">All subjects</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->subject_id }}" @selected($selectedSubjectId === $subject->subject_id)>
                            {{ $subject->subject_code }} - {{ $subject->subject_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($selectedTeacherId || $selectedSubjectId)
                <div class="record-filter-actions">
                    <a
                        aria-label="Clear report filters"
                        class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                        href="{{ route('department-chair.reports', ['type' => $activeReportType]) }}"
                        title="Clear filters"
                    >
                        <span class="material-symbols-outlined fs-6">filter_alt_off</span>
                    </a>
                </div>
            @endif
        </form>
    </section>

    <div class="table-switch-tabs mt-3">
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
        <section class="report-table-card" data-report-panel="{{ $type }}" @if ($activeReportType !== $type) hidden @endif>
            <div class="report-table-header">
                <div>
                    <h3 class="brand-text h3 mb-1">{{ $group['label'] }}</h3>
                </div>
                <span class="badge text-bg-light rounded-1">{{ $group['items']->count() }} {{ $group['items']->count() === 1 ? 'report' : 'reports' }}</span>
            </div>

            @if ($group['items']->isNotEmpty())
                <div class="table-responsive">
                    <table class="table reports-table mb-0 compact-data-table">
                        <thead>
                            <tr>
                                <th>Assessment</th>
                                <th>Subject / Class</th>
                                <th>Instructor</th>
                                <th>Finalized</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['items'] as $report)
                                @php
                                    $publishAssessment = $report->publishAssessment;
                                    $assessment = $publishAssessment?->assessment;
                                    $class = $publishAssessment?->class;
                                    $instructor = $assessment?->instructorProfile?->user;
                                @endphp
                                <tr>
                                    <td>
                                        <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $assessment?->title ?? 'Untitled assessment' }}</p>
                                        <p class="small text-secondary mb-0">Term: {{ ucfirst((string) $assessment?->reporting_term) }}</p>
                                    </td>
                                    <td>
                                        <p class="fw-semibold mb-1">{{ $assessment?->subject?->subject_code ?? $class?->subject?->subject_code ?? 'No subject' }}</p>
                                        <p class="small text-secondary mb-0">{{ $class?->class_name ?? 'No class' }} {{ $class?->school_year ? '| '.$class->school_year : '' }}</p>
                                    </td>
                                    <td>{{ $instructor?->displayName() ?? 'No instructor' }}</td>
                                    <td>{{ $report->updated_at?->format('M d, Y h:i A') }}</td>
                                    <td><span class="report-badge">Finalized</span></td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" href="{{ route('department-chair.reports.show', [$publishAssessment, $report->report_type]) }}">
                                            <span class="material-symbols-outlined fs-6">visibility</span>
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4">
                    <div class="report-empty">
                        <h4 class="h5 mb-2" style="color: var(--psu-navy);">No finalized {{ strtolower($group['label']) }} yet</h4>
                    </div>
                </div>
            @endif
        </section>
    @endforeach
@endsection
