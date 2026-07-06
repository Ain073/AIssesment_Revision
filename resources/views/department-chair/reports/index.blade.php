@extends('layouts.portal')

@section('title', 'Reports | AIssessment Department Chair')
@section('header', 'Reports')

@php
    $activeReportType = request()->query('type') === 'summative' ? 'summative' : 'formative';
    $reportGroups = [
        'formative' => [
            'label' => 'Formative Reports',
            'icon' => 'description',
            'items' => $formativeReports,
            'helper' => 'Finalized formative reports from instructors in your department.',
        ],
        'summative' => [
            'label' => 'Summative Reports',
            'icon' => 'assignment',
            'items' => $summativeReports,
            'helper' => 'Finalized summative reports from instructors in your department.',
        ],
    ];
@endphp

@push('styles')
    <style>
        .reports-hero,
        .report-table-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 16px 32px rgba(0, 26, 112, 0.06);
        }

        .reports-hero {
            padding: 1.75rem;
            background: linear-gradient(118deg, var(--psu-navy) 0%, var(--psu-navy-2) 62%, #918a6d 150%);
            color: #fff;
        }

        .reports-hero p {
            color: rgba(255, 255, 255, 0.82);
            max-width: 70ch;
            margin-bottom: 0;
        }

        .report-table-card {
            overflow: hidden;
            margin-top: 1.25rem;
        }

        .report-table-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 68%, #8f8a73 120%);
            color: #fff;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
        }

        .reports-table th {
            background: #edf2ff;
            color: var(--psu-muted);
            font-size: 0.78rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 1rem;
        }

        .reports-table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .report-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.35rem 0.65rem;
            background: #dcfce7;
            color: #166534;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-empty {
            border: 1px dashed #b9c5e7;
            border-radius: 0.5rem;
            background: #fbfcff;
            padding: 2rem;
            text-align: center;
        }

    </style>
@endpush

@section('content')
    <section class="reports-hero">
        <p class="small fw-bold text-uppercase mb-2" style="color: rgba(255, 245, 191, 0.92);">Department Chair Module</p>
        <h2 class="brand-text h1 mb-3">Finalized Reports</h2>
        <p>
            These are finalized formative and summative reports submitted by instructors under
            {{ $department?->dept_name ?? 'your assigned department' }}.
        </p>
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
                    <p class="small text-white-50 mb-0">{{ $group['helper'] }}</p>
                </div>
                <span class="badge text-bg-light rounded-1">{{ $group['items']->count() }} {{ $group['items']->count() === 1 ? 'report' : 'reports' }}</span>
            </div>

            @if ($group['items']->isNotEmpty())
                <div class="table-responsive">
                    <table class="table reports-table mb-0">
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
                                    $classAssessment = $report->classAssessment;
                                    $assessment = $classAssessment?->assessment;
                                    $class = $classAssessment?->class;
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
                                        <a class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" href="{{ route('department-chair.reports.show', [$classAssessment, $report->report_type]) }}">
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
                        <p class="text-secondary mb-0">Reports will appear here after instructors finalize them.</p>
                    </div>
                </div>
            @endif
        </section>
    @endforeach
@endsection
