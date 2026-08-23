@extends('layouts.portal')

@section('title', 'Check Submission | AIssessment Instructor')
@section('header', 'Check Submission')

@push('styles')
    <style>
        .grading-panel,
        .grading-summary {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .grading-panel-header {
            background: linear-gradient(90deg, var(--psu-navy), var(--psu-navy-2));
            color: #fff;
            padding: 1rem 1.25rem;
        }

        .answer-box {
            background: #f8faff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            min-height: 3.25rem;
            padding: 0.85rem 1rem;
            white-space: pre-wrap;
        }

        .score-input {
            max-width: 9rem;
        }

        @media (max-width: 575.98px) {
            .score-input {
                max-width: none;
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.assessments.results', $classAssessment) }}" style="color: var(--psu-navy-2);">
                <span class="material-symbols-outlined align-middle fs-6">arrow_back</span>
                Back to Results
            </a>
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">{{ $assessment?->title ?? 'Assessment' }}</h1>
            <p class="text-secondary mb-0">
                {{ $student?->user?->displayName() ?? 'Student account' }}
                @if ($student?->student_number)
                    | {{ $student->student_number }}
                @endif
                | Attempt {{ $submission->attempt_number }}
            </p>
        </div>

        <section class="grading-summary px-4 py-3">
            <p class="small fw-bold text-secondary text-uppercase mb-1">Current Score</p>
            <p class="h4 fw-bold mb-0" style="color: var(--psu-navy);">{{ $scoreText }} / {{ $maxScoreText }}</p>
            @if ($pendingEssayCount > 0)
                <span class="badge text-bg-warning rounded-1 mt-2">{{ $pendingEssayCount }} essay pending</span>
            @endif
        </section>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            Please check the highlighted essay scores.
        </div>
    @endif

    <form action="{{ route('instructor.assessments.submissions.grade.update', $submission) }}" method="POST">
        @csrf
        @method('PUT')

        <section class="grading-panel overflow-hidden">
            <div class="grading-panel-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h5 mb-0">Answers</h2>
                <span class="badge text-bg-light border">{{ $rows->count() }} questions</span>
            </div>

            <div class="d-grid gap-3 p-3 p-lg-4">
                @foreach ($rows as $row)
                    @php
                        $item = $row['item'];
                        $answer = $row['answer'];
                        $answerId = $answer?->submission_answer_id;
                        $studentAnswer = $answer?->choice?->choice_text
                            ?? (filled($answer?->answer_text) ? $answer->answer_text : 'No answer');
                        $maxPoints = (float) $item->points;
                        $earnedPoints = $row['earned_points'];
                        $isEssay = $item->item_type === 'essay';
                        $scoreField = $answerId ? "essay_scores.{$answerId}" : null;
                        $feedbackField = $answerId ? "essay_feedback.{$answerId}" : null;
                    @endphp

                    <article class="border rounded-2 overflow-hidden">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3" style="background:#edf2ff;">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <span class="badge rounded-pill px-3 py-2" style="background: var(--psu-navy);">Question {{ $loop->iteration }}</span>
                                <span class="badge text-bg-light border rounded-pill px-3 py-2">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span>
                                <span class="badge text-bg-light border rounded-pill px-3 py-2">{{ $maxPoints }} pts</span>
                            </div>

                            @if (! $isEssay)
                                <span class="badge {{ $row['is_correct'] ? 'text-bg-success' : 'text-bg-danger' }} rounded-1 px-3 py-2">
                                    {{ $earnedPoints }} / {{ $maxPoints }}
                                </span>
                            @elseif ($answer?->earned_points !== null)
                                <span class="badge text-bg-success rounded-1 px-3 py-2">Checked</span>
                            @else
                                <span class="badge text-bg-warning rounded-1 px-3 py-2">Needs checking</span>
                            @endif
                        </div>

                        <div class="p-3 p-lg-4">
                            <h3 class="h5 fw-bold mb-3" style="color: var(--psu-navy);">{{ $item->question_text }}</h3>

                            <div class="row g-3">
                                <div class="col-lg-{{ $isEssay ? '8' : '12' }}">
                                    <p class="small fw-bold text-secondary text-uppercase mb-2">Student Answer</p>
                                    <div class="answer-box">{{ $studentAnswer }}</div>
                                </div>

                                @if ($isEssay && $answerId)
                                    <div class="col-lg-4">
                                        <label class="form-label small fw-bold text-secondary text-uppercase" for="essayScore{{ $answerId }}">
                                            Score
                                        </label>
                                        <div class="input-group score-input">
                                            <input
                                                class="form-control @error($scoreField) is-invalid @enderror"
                                                id="essayScore{{ $answerId }}"
                                                name="essay_scores[{{ $answerId }}]"
                                                type="number"
                                                min="0"
                                                max="{{ $maxPoints }}"
                                                step="0.01"
                                                value="{{ old("essay_scores.$answerId", $answer->earned_points) }}"
                                                required
                                            >
                                            <span class="input-group-text">/ {{ $maxPoints }}</span>
                                            @error($scoreField)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <label class="form-label small fw-bold text-secondary text-uppercase mt-3" for="essayFeedback{{ $answerId }}">
                                            Feedback
                                        </label>
                                        <textarea
                                            class="form-control @error($feedbackField) is-invalid @enderror"
                                            id="essayFeedback{{ $answerId }}"
                                            name="essay_feedback[{{ $answerId }}]"
                                            rows="4"
                                            placeholder="Optional feedback"
                                        >{{ old("essay_feedback.$answerId", $answer->feedback) }}</textarea>
                                        @error($feedbackField)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-secondary px-4" href="{{ route('instructor.assessments.results', $classAssessment) }}">Cancel</a>
            <button class="btn btn-psu px-4 d-inline-flex align-items-center gap-2" type="submit">
                <span class="material-symbols-outlined fs-5">save</span>
                Save Scores
            </button>
        </div>
    </form>
@endsection
