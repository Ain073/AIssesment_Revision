@extends('layouts.portal')

@section('title', 'Assessment Submitted | AIssessment Student')
@section('header', 'Assessment Submitted')

@push('styles')
    <style>
        .submitted-panel {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.06);
        }

        .submitted-check {
            width: 5rem;
            height: 5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #e7f8ef;
            color: #168a4a;
            border: 1px solid #bce8cc;
        }

        .submitted-score {
            background: #f8faff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }

        .answer-review-card {
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
        }

        .answer-text-box {
            min-height: 3.25rem;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #f8faff;
            padding: 0.9rem 1rem;
        }

        .submitted-results-modal .modal-content {
            border: 0;
            border-radius: 0.5rem;
            box-shadow: 0 24px 60px rgba(0, 26, 112, 0.24);
        }

        .submitted-results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 58%, rgba(117, 125, 145, 0.92) 100%);
            color: #fff;
            padding: 1rem 1.5rem;
        }

        .submitted-results-title {
            min-width: 0;
        }

        .submitted-results-title h2 {
            line-height: 1.1;
        }

        .submitted-results-meta {
            overflow-wrap: anywhere;
        }

        .submitted-results-actions {
            flex: 0 0 auto;
        }

        @media (max-width: 575.98px) {
            .submitted-results-header {
                align-items: flex-start;
                padding: 0.85rem 1rem;
            }

            .submitted-results-title h2 {
                font-size: 1.35rem;
            }

            .submitted-results-meta {
                font-size: 0.78rem;
            }

            .submitted-results-actions .badge {
                padding: 0.45rem 0.6rem !important;
                font-size: 0.72rem;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $canViewResult = $showScore || $showAnswers;
        $resultBadge = $passed ? 'text-bg-success' : 'text-bg-danger';
    @endphp

    <section class="submitted-panel p-4 p-lg-5 text-center mb-4">
        <div class="submitted-check mb-4">
            <span class="material-symbols-outlined" style="font-size: 3rem;">check</span>
        </div>

        <p class="small fw-bold text-uppercase mb-2" style="color: var(--psu-navy-2);">Submission Complete</p>
        <h1 class="brand-text mb-2" style="color: var(--psu-navy);">Your Assessment is Submitted!</h1>
        <p class="text-secondary mb-1">{{ $assessment?->title ?? 'Assessment' }}</p>
        <p class="small text-secondary mb-4">
            Submitted {{ $submission->submitted_at?->format('M d, Y h:i A') ?? 'recently' }}
            | Attempt {{ $submission->attempt_number }}
        </p>

        @if ($submission->completion_reason === \App\Models\Submission::COMPLETION_WARNING_LIMIT)
            <div class="alert alert-warning text-start mx-auto mb-4" style="max-width: 720px;">
                This assessment was submitted automatically after reaching the warning limit.
            </div>
        @endif

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a class="btn btn-outline-secondary portal-ajax-link px-4" href="{{ route('student.assessments') }}">
                Back to Assessments
            </a>

            @if ($canViewResult)
                <button class="btn btn-psu px-4 d-inline-flex align-items-center gap-2" data-bs-target="#submissionResultModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">visibility</span>
                    View Results
                </button>
            @else
                <a class="btn btn-outline-primary portal-ajax-link px-4" href="{{ route('student.results') }}">
                    My Results
                </a>
            @endif
        </div>
    </section>

    @if ($canViewResult)
        <div class="modal fade submitted-results-modal" id="submissionResultModal" tabindex="-1" aria-labelledby="submissionResultTitle" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content overflow-hidden">
                    <div class="submitted-results-header">
                        <div class="submitted-results-title">
                            <h2 class="h3 brand-text mb-1" id="submissionResultTitle">Submission Results</h2>
                            <p class="submitted-results-meta text-white-50 mb-0">{{ $class?->class_name ?? 'Class' }} - {{ $assessment?->subject?->subject_code ?? 'No subject' }}</p>
                        </div>
                        <div class="submitted-results-actions d-flex align-items-center gap-2">
                            @if ($showScore)
                                <span class="badge {{ $resultBadge }} rounded-1 px-3 py-2">{{ $passed ? 'Passed' : 'Failed' }}</span>
                            @endif
                            <button class="btn btn-sm btn-light border d-inline-flex align-items-center justify-content-center" data-bs-dismiss="modal" type="button" aria-label="Close results">
                                <span class="material-symbols-outlined fs-6">close</span>
                            </button>
                        </div>
                    </div>

                    <div class="modal-body p-4">
                        @if ($showScore)
                            <div class="submitted-score p-4 mb-4">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-4">
                                        <p class="small fw-bold text-secondary text-uppercase mb-1">Score</p>
                                        <p class="h2 fw-bold mb-0" style="color: var(--psu-navy);">{{ $scoreText }} / {{ $maxScoreText }}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="small fw-bold text-secondary text-uppercase mb-1">Percentage</p>
                                        <p class="h2 fw-bold mb-0 {{ $passed ? 'text-success' : 'text-danger' }}">{{ $percentage }}%</p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="small fw-bold text-secondary text-uppercase mb-1">Warnings</p>
                                        <p class="h2 fw-bold mb-0" style="color: var(--psu-navy);">{{ $submission->warning_count }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($showAnswers)
                            <div class="d-grid gap-3">
                                @foreach ($answerRows as $row)
                                    @php
                                        $item = $row['item'];
                                        $answer = $row['answer'];
                                        $isEssay = $item->item_type === 'essay';
                                    @endphp

                                    <article class="answer-review-card p-4">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="badge rounded-pill px-3 py-2" style="background: var(--psu-navy);">Question {{ $loop->iteration }}</span>
                                                <span class="badge text-bg-light border rounded-pill px-3 py-2">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span>
                                                @if ($showScore)
                                                    <span class="badge text-bg-light border rounded-pill px-3 py-2">
                                                        {{ $row['earned_points'] }} / {{ $item->points }} pts
                                                    </span>
                                                @endif
                                            </div>
                                            @if ($isEssay && $answer?->earned_points === null)
                                                <span class="badge text-bg-warning rounded-1 px-3 py-2">Pending check</span>
                                            @elseif ($isEssay)
                                                <span class="badge text-bg-success rounded-1 px-3 py-2">Checked</span>
                                            @else
                                                <span class="badge {{ $row['is_correct'] ? 'text-bg-success' : 'text-bg-danger' }} rounded-1 px-3 py-2">
                                                    {{ $row['is_correct'] ? 'Correct' : 'Incorrect' }}
                                                </span>
                                            @endif
                                        </div>

                                        <h3 class="h5 fw-bold mb-3" style="color: var(--psu-navy);">{{ $item->question_text }}</h3>

                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <p class="small fw-bold text-secondary text-uppercase mb-2">Your Answer</p>
                                                <div class="answer-text-box">{{ $row['student_answer'] }}</div>
                                            </div>
                                            <div class="col-lg-6">
                                                <p class="small fw-bold text-secondary text-uppercase mb-2">Correct Answer</p>
                                                <div class="answer-text-box">{{ $row['correct_answer'] }}</div>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="text-secondary mb-0">Your teacher allowed score viewing, but correct answers are hidden for this assessment.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <section class="submitted-panel p-4 text-center">
            <h2 class="h4 brand-text mb-2" style="color: var(--psu-navy);">Results are not available yet</h2>
            <p class="text-secondary mb-0">Your teacher has not allowed immediate score or answer viewing for this assessment.</p>
        </section>
    @endif
@endsection
