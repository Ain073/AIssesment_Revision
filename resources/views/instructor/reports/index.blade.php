@extends('layouts.portal')

@section('title', 'Reports | AIssessment Instructor')
@section('header', 'Reports')

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
    <style>
        .reports-panel,
        .report-assessment-card {
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .reports-panel {
            overflow: hidden;
        }

        .reports-panel-header {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(90deg, var(--psu-navy), var(--psu-navy-2));
            color: #fff;
            padding: 1rem 1.25rem;
        }

        .report-assessment-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 1rem;
            align-items: flex-start;
            padding: 1rem;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .report-assessment-card:has(.form-check-input:checked) {
            border-color: var(--psu-navy-2);
            box-shadow: 0 16px 30px rgba(9, 39, 216, 0.1);
        }

        .report-check {
            width: 1.15rem;
            height: 1.15rem;
        }

        .report-meta-line {
            color: var(--psu-muted);
            font-size: 0.92rem;
        }

        .report-badge {
            border-radius: 999px;
            padding: 0.4rem 0.7rem;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-badge.draft {
            background: #fff4bf;
            color: #765500;
        }

        .report-badge.finalized {
            background: #dcfce7;
            color: #166534;
        }

        .report-badge.none {
            background: #edf3ff;
            color: var(--psu-navy);
        }

        .report-empty {
            border: 1px dashed #b9c5e7;
            border-radius: 0.5rem;
            background: #fbfcff;
            color: var(--psu-muted);
            padding: 1.25rem;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .report-assessment-card {
                grid-template-columns: auto minmax(0, 1fr);
                gap: 0.75rem;
                padding: 0.85rem;
            }

            .report-assessment-card .report-badge {
                grid-column: 2;
                justify-self: start;
            }

            .reports-panel-header {
                padding: 0.85rem 1rem;
            }

            .reports-panel-header h2 {
                font-size: 1.05rem;
            }

            .report-meta-line {
                font-size: 0.78rem;
                line-height: 1.35;
                overflow-wrap: anywhere;
            }

            .report-badge {
                padding: 0.32rem 0.55rem;
                font-size: 0.66rem;
                white-space: normal;
            }

            .reports-panel form {
                padding: 0.85rem !important;
            }

            .reports-panel .btn {
                min-height: 2.35rem;
                font-size: 0.84rem;
            }
        }

        @media (max-width: 575.98px) {
            .table-switch-tabs {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .table-switch-button {
                min-height: 2.5rem;
                justify-content: center;
                font-size: 0.78rem;
                white-space: normal;
            }
        }
    </style>
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
                        @foreach ($group['items'] as $classAssessment)
                            @php
                                $assessment = $classAssessment->assessment;
                                $class = $classAssessment->class;
                                $report = $classAssessment->report;
                            @endphp
                            <label class="report-assessment-card mb-0">
                                <input
                                    class="form-check-input report-check mt-1"
                                    name="class_assessment_keys[]"
                                    type="checkbox"
                                    value="{{ $classAssessment->public_id }}"
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
                                        {{ $classAssessment->due_at ? $classAssessment->due_at->format('M d, Y h:i A') : 'No due date' }}
                                        | Submissions: {{ $classAssessment->submissions_count }}
                                        | Term: {{ ucfirst((string) $assessment->reporting_term) }}
                                    </span>
                                </span>
                                @if ($report)
                                    <span class="report-badge {{ $report->report_status === \App\Models\Report::STATUS_FINALIZED ? 'finalized' : 'draft' }}">
                                        {{ ucfirst($report->report_status) }}
                                    </span>
                                @else
                                    <span class="report-badge none">Not Prepared</span>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button class="btn btn-psu d-inline-flex align-items-center gap-2" type="submit" data-report-submit="{{ $type }}" disabled>
                            <span class="material-symbols-outlined fs-5">post_add</span>
                            Prepare Selected
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
    <script>
        window.initializeReportsPage?.();
    </script>
@endsection

@push('scripts')
    @include('instructor.reports.reports-page-code')
@endpush
