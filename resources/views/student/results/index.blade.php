@extends('layouts.portal')

@section('title', 'Results | AIssessment Student')
@section('header', 'Results')

@push('styles')
    <style>
        .result-card,
        .results-panel {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .result-card {
            height: 100%;
            padding: 1.25rem;
        }

        .result-label {
            color: #5f6f87;
            font-size: 0.78rem;
            font-weight: 800;
            margin-bottom: 0.55rem;
            text-transform: uppercase;
        }

        .result-value {
            color: var(--psu-navy);
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .class-filter-button {
            min-width: 210px;
            justify-content: space-between;
        }

        .class-filter-menu {
            max-height: 320px;
            overflow-y: auto;
            width: 280px;
        }

        .results-panel {
            overflow: hidden;
        }

        .results-header {
            background: linear-gradient(90deg, var(--psu-navy), var(--psu-navy-2));
            color: #fff;
            padding: 1.25rem 1.5rem;
        }

        .results-table {
            min-width: 980px;
        }

        .results-table thead th {
            background: #edf2ff;
            color: #53627a;
            font-size: 0.76rem;
            font-weight: 800;
            padding: 0.95rem 1rem;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .results-table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .score-meter {
            background: #e5e7eb;
            border-radius: 999px;
            height: 0.55rem;
            overflow: hidden;
            width: 150px;
        }

        .score-meter-fill {
            border-radius: inherit;
            height: 100%;
        }

        .empty-icon {
            align-items: center;
            background: var(--psu-gold-soft);
            border-radius: 50%;
            color: var(--psu-navy-2);
            display: inline-flex;
            height: 58px;
            justify-content: center;
            width: 58px;
        }

        @media (max-width: 767.98px) {
            .result-value {
                font-size: 1.7rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="brand-text mb-1" style="color: var(--psu-navy);">My Results</h1>
        </div>
        <div class="dropdown">
            <button
                class="btn btn-outline-primary class-filter-button d-inline-flex align-items-center gap-2"
                data-bs-toggle="dropdown"
                type="button"
                aria-expanded="false"
            >
                <span class="material-symbols-outlined fs-5">school</span>
                <span class="text-truncate">{{ $selectedClassLabel }}</span>
                <span class="material-symbols-outlined fs-5 ms-auto">expand_more</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end class-filter-menu shadow-sm">
                <a class="dropdown-item results-class-filter-link {{ $selectedClassKey === '' ? 'active' : '' }}" href="{{ route('student.results') }}">
                    All Classes
                </a>
                @foreach ($classOptions as $classOption)
                    <a
                        class="dropdown-item results-class-filter-link {{ $selectedClassKey === $classOption['key'] ? 'active' : '' }}"
                        href="{{ route('student.results', ['class' => $classOption['key']]) }}"
                    >
                        <span class="fw-semibold d-block">{{ $classOption['label'] }}</span>
                        <span class="small">{{ $classOption['subject'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <section class="result-card">
                <p class="result-label">Submitted Assessments</p>
                <div class="result-value">{{ $summary['submitted_assessments'] }}</div>
            </section>
        </div>
        <div class="col-md-4">
            <section class="result-card">
                <p class="result-label">Passed</p>
                <div class="result-value">{{ $summary['passed_count'] }}</div>
            </section>
        </div>
        <div class="col-md-4">
            <section class="result-card">
                <p class="result-label">Failed</p>
                <div class="result-value">{{ $summary['failed_count'] }}</div>
            </section>
        </div>
    </div>

    <section class="results-panel">
        <div class="results-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="brand-text h4 mb-1">Assessment Results</h2>
            </div>
            <span class="badge text-bg-light border">{{ $results->count() }} record{{ $results->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($results->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 results-table">
                    <thead>
                        <tr>
                            <th>Assessment</th>
                            <th>Class</th>
                            <th>Best Score</th>
                            <th>Result</th>
                            <th>Attempts</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $result)
                            @php
                                $assessment = $result['assessment'];
                                $class = $result['class'];
                                $bestAttempt = $result['best_attempt'];
                                $scoreVisible = $result['score_visible'];
                                $percentage = (float) $result['percentage'];
                                $barColor = $percentage >= 75 ? '#198754' : '#dc3545';
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-bold" style="color: var(--psu-navy);">{{ $assessment?->title ?? 'Untitled Assessment' }}</div>
                                    <div class="small text-secondary">{{ $assessment?->subject?->subject_code ?? $class?->subject?->subject_code ?? 'No subject' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $class?->class_name ?? 'Class removed' }}</div>
                                    <div class="small text-secondary">{{ $class?->instructorProfile?->user?->displayName() ?? 'No instructor' }}</div>
                                </td>
                                <td>
                                    @if ($scoreVisible && $bestAttempt)
                                        <div class="fw-bold" style="color: var(--psu-navy);">
                                            {{ $bestAttempt['score_text'] }} / {{ $result['max_score_text'] }}
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-2">
                                            <div class="score-meter" aria-hidden="true">
                                                <div class="score-meter-fill" style="width: {{ min($percentage, 100) }}%; background: {{ $barColor }};"></div>
                                            </div>
                                            <span class="small fw-bold" style="color: {{ $barColor }};">{{ $percentage }}%</span>
                                        </div>
                                    @else
                                        <span class="badge text-bg-secondary rounded-1">Waiting for release</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! $scoreVisible || ! $bestAttempt)
                                        <span class="badge text-bg-secondary rounded-1">Pending</span>
                                    @elseif ($result['passed'])
                                        <span class="badge text-bg-success rounded-1">Passed</span>
                                    @else
                                        <span class="badge text-bg-danger rounded-1">Failed</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $result['attempt_count'] }}</span>
                                    @if ($bestAttempt && $bestAttempt['completion_reason'] === \App\Models\Submission::COMPLETION_WARNING_LIMIT)
                                        <span class="badge text-bg-warning rounded-1 d-block mt-1">Auto-submitted</span>
                                    @endif
                                    @if ($bestAttempt && $bestAttempt['warning_count'] > 0)
                                        <span class="small text-secondary d-block mt-1">{{ $bestAttempt['warning_count'] }} warning{{ $bestAttempt['warning_count'] === 1 ? '' : 's' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($bestAttempt && $bestAttempt['submitted_at'])
                                        <span class="small d-block">{{ $bestAttempt['submitted_at']->format('M d, Y') }}</span>
                                        <span class="small text-secondary d-block">{{ $bestAttempt['submitted_at']->format('h:i A') }}</span>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-5 text-center">
                <div class="empty-icon mb-3 mx-auto">
                    <span class="material-symbols-outlined fs-2">grading</span>
                </div>
                <h2 class="h4" style="color: var(--psu-navy);">No results yet</h2>
            </div>
        @endif
    </section>
@endsection
