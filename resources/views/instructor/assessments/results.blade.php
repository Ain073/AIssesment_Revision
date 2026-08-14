@extends('layouts.portal')

@section('title', ($assessment?->title ?? 'Assessment') . ' Results | AIssessment Instructor')
@section('header', 'Assessment Results')

@push('styles')
    <style>
        .result-summary,
        .results-panel {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .result-summary {
            padding: 1.25rem;
            height: 100%;
        }

        .results-panel {
            overflow: hidden;
        }

        .results-panel-header {
            background: linear-gradient(90deg, var(--psu-navy), var(--psu-navy-2));
            color: #fff;
            padding: 1rem 1.25rem;
        }

        .results-table {
            min-width: 980px;
        }

        .results-table thead th {
            background: #edf2ff;
            color: var(--psu-muted);
            font-size: 0.76rem;
            text-transform: uppercase;
            padding: 0.9rem 1rem;
        }

        .results-table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .student-avatar {
            align-items: center;
            background: var(--psu-gold-soft);
            border-radius: 50%;
            color: var(--psu-navy);
            display: inline-flex;
            flex: 0 0 42px;
            font-weight: 800;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .security-history summary {
            color: var(--psu-navy-2);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .security-history-list {
            border-left: 2px solid var(--psu-line);
            margin: 0.6rem 0 0 0.3rem;
            padding-left: 0.8rem;
            width: 250px;
        }

        .security-history-item + .security-history-item {
            margin-top: 0.6rem;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.assessments', ['tab' => 'published']) }}" style="color: var(--psu-navy-2);">
                <span class="material-symbols-outlined align-middle fs-6">arrow_back</span>
                Back to Published Assessments
            </a>
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">{{ $assessment?->title ?? 'Untitled Assessment' }}</h1>
            <p class="text-secondary mb-0">
                {{ $assessment?->subject?->subject_code ?? 'No subject' }}
                @if ($class)
                    | {{ $class->class_name }}
                @endif
                @if ($class?->school_year)
                    | {{ $class->school_year }}
                @endif
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-success rounded-1 align-self-center px-3 py-2">Completed</span>
            <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.show', $assessment) }}">
                <span class="material-symbols-outlined fs-5">description</span>
                Assessment Content
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <section class="result-summary">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Students Submitted</p>
                <div class="h2 fw-bold mb-1" style="color: var(--psu-navy);">{{ $analytics['takers_count'] }} / {{ $analytics['students_count'] }}</div>
            </section>
        </div>
        <div class="col-md-6">
            <section class="result-summary">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Auto Submitted</p>
                <div class="h2 fw-bold mb-1" style="color: var(--psu-navy);">{{ $autoSubmittedCount }}</div>
            </section>
        </div>
    </div>

    <section class="results-panel">
        <div class="results-panel-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-1">Student Results</h2>
            </div>
            <span class="badge text-bg-light border">{{ $studentResults->count() }} students</span>
        </div>

        @if ($studentResults->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 results-table mobile-result-table student-result-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Security</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($studentResults as $result)
                            @php
                                $student = $result['student'];
                                $bestAttempt = $result['best_attempt'];
                                $displayName = $student->user?->displayName() ?? 'Student account';
                                $submittedAttemptsCount = $result['submitted_attempts_count'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="student-avatar">{{ strtoupper(mb_substr($displayName, 0, 1)) }}</span>
                                        <span>
                                            <span class="fw-bold d-block" style="color: var(--psu-navy);">{{ $displayName }}</span>
                                            <span class="small text-secondary">{{ $student->student_number ?? 'No student number' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $bestAttempt ? $bestAttempt['score'].' / '.$analytics['max_score'] : '-' }}</span>
                                    @if ($submittedAttemptsCount > 1)
                                        <span class="small text-secondary d-block">Best of {{ $submittedAttemptsCount }} attempts</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! $bestAttempt)
                                        <span class="badge text-bg-secondary rounded-1">Not submitted</span>
                                    @elseif ($bestAttempt['passed'])
                                        <span class="badge text-bg-success rounded-1">Passed</span>
                                    @else
                                        <span class="badge text-bg-danger rounded-1">Failed</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! empty($bestAttempt['submitted_at']))
                                        <span class="small d-block">{{ $bestAttempt['submitted_at']->format('M d, Y') }}</span>
                                        <span class="small text-secondary d-block">{{ $bestAttempt['submitted_at']->format('h:i A') }}</span>
                                        @if ($bestAttempt['completion_reason'] === \App\Models\Submission::COMPLETION_WARNING_LIMIT)
                                            <span class="badge text-bg-warning rounded-1 mt-1">Auto-submitted</span>
                                        @endif
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $securityEvents = $result['attempts']->flatMap(fn ($attempt) => $attempt['security_events']);
                                    @endphp
                                    <span class="fw-bold">{{ $result['attempts']->sum('warning_count') }}</span>
                                    @if ($securityEvents->isNotEmpty())
                                        <details class="security-history mt-1">
                                            <summary>{{ $securityEvents->count() }} recorded event{{ $securityEvents->count() === 1 ? '' : 's' }}</summary>
                                            <div class="security-history-list">
                                                @foreach ($securityEvents as $event)
                                                    <div class="security-history-item">
                                                        <span class="fw-semibold small d-block">{{ $event['label'] }}</span>
                                                        <span class="text-secondary small">{{ $event['occurred_at']?->format('M d, Y h:i:s A') }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @else
                                        <span class="small text-secondary d-block">No recorded events</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-5 text-center">
                <span class="material-symbols-outlined fs-1 text-secondary">group_off</span>
                <h2 class="h5 mt-2" style="color: var(--psu-navy);">No students in this class</h2>
            </div>
        @endif
    </section>
@endsection
