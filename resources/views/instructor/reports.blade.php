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
            'helper' => 'Completed quizzes, activities, and other formative assessments appear here.',
        ],
        'summative' => [
            'label' => 'Summative Reports',
            'icon' => 'assignment',
            'items' => $summativeAssessments,
            'helper' => 'Completed exams and summative assessments appear here.',
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

        .reports-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
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
            }

            .report-assessment-card .report-badge {
                grid-column: 2;
                justify-self: start;
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
            <p class="text-secondary mb-0">Select completed assessments for formative or summative report preparation.</p>
        </div>
        <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments', ['tab' => 'published']) }}">
            <span class="material-symbols-outlined fs-5">assignment</span>
            Published Assessments
        </a>
    </div>

    <div class="reports-tabs mb-3">
        @foreach ($reportGroups as $type => $group)
            <button
                class="btn {{ $activeReportType === $type ? 'btn-psu' : 'btn-outline-primary' }} d-inline-flex align-items-center gap-2"
                data-report-tab="{{ $type }}"
                type="button"
            >
                <span class="material-symbols-outlined fs-5">{{ $group['icon'] }}</span>
                {{ $group['label'] }}
                <span class="badge text-bg-light border">{{ $group['items']->count() }}</span>
            </button>
        @endforeach
    </div>

    @foreach ($reportGroups as $type => $group)
        <section class="reports-panel mb-4" data-report-panel="{{ $type }}" @if ($activeReportType !== $type) hidden @endif>
            <div class="reports-panel-header">
                <div>
                    <h2 class="h4 mb-1">{{ $group['label'] }}</h2>
                    <p class="small text-white-50 mb-0">{{ $group['helper'] }}</p>
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
                                    <span class="report-badge draft">{{ ucfirst($report->report_status) }}</span>
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
    <script>
        window.initializeReportsPage = () => {
            const tabs = Array.from(document.querySelectorAll('[data-report-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-report-panel]'));

            if (tabs.length === 0 && panels.length === 0) {
                return;
            }

            const setActivePanel = (type) => {
                tabs.forEach((tab) => {
                    const isActive = tab.dataset.reportTab === type;
                    tab.classList.toggle('btn-psu', isActive);
                    tab.classList.toggle('btn-outline-primary', ! isActive);
                });

                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.reportPanel !== type;
                });
            };

            const syncSelection = (type) => {
                const checkboxes = Array.from(document.querySelectorAll(`[data-report-checkbox="${type}"]`));
                const checked = checkboxes.filter((checkbox) => checkbox.checked);
                const countTarget = document.querySelector(`[data-report-selected-count="${type}"]`);
                const submitButton = document.querySelector(`[data-report-submit="${type}"]`);
                const selectAll = document.querySelector(`[data-report-select-all="${type}"]`);

                if (countTarget) {
                    countTarget.textContent = `${checked.length} selected`;
                }

                if (submitButton) {
                    submitButton.disabled = checked.length === 0;
                }

                if (selectAll) {
                    selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
                    selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
                }
            };

            tabs.forEach((tab) => {
                if (tab.dataset.reportBound === 'true') {
                    return;
                }

                tab.dataset.reportBound = 'true';
                tab.addEventListener('click', () => setActivePanel(tab.dataset.reportTab));
            });

            document.querySelectorAll('[data-report-select-all]').forEach((selectAll) => {
                if (selectAll.dataset.reportBound === 'true') {
                    return;
                }

                selectAll.dataset.reportBound = 'true';
                selectAll.addEventListener('change', () => {
                    const type = selectAll.dataset.reportSelectAll;
                    document.querySelectorAll(`[data-report-checkbox="${type}"]`).forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });
                    syncSelection(type);
                });
            });

            document.querySelectorAll('[data-report-checkbox]').forEach((checkbox) => {
                if (checkbox.dataset.reportBound === 'true') {
                    return;
                }

                checkbox.dataset.reportBound = 'true';
                checkbox.addEventListener('change', () => syncSelection(checkbox.dataset.reportCheckbox));
                checkbox.addEventListener('click', () => syncSelection(checkbox.dataset.reportCheckbox));
            });

            document.querySelectorAll('[data-report-select-form]').forEach((form) => {
                if (form.dataset.reportSubmitBound === 'true') {
                    return;
                }

                form.dataset.reportSubmitBound = 'true';
                form.addEventListener('submit', (event) => {
                    const type = form.dataset.reportSelectForm;
                    const checked = form.querySelectorAll(`[data-report-checkbox="${type}"]:checked`);

                    if (checked.length === 0) {
                        event.preventDefault();
                        syncSelection(type);
                    }
                });
            });

            ['formative', 'summative'].forEach(syncSelection);
        };

        window.initializeReportsPage();
    </script>
@endpush
