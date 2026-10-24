@extends('layouts.portal')

@section('title', 'Results | AIssessment Student')
@section('header', 'Results')

@push('styles')
    <x-page-style path="css/pages/student/results/index.css" />
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
                <a class="dropdown-item results-class-filter-link {{ $selectedClassKey === '' ? 'active' : '' }}" href="{{ route('student.results', ['search' => \App\Support\RecordSearch::term()]) }}">
                    All Classes
                </a>
                @foreach ($classOptions as $classOption)
                    <a
                        class="dropdown-item results-class-filter-link {{ $selectedClassKey === $classOption['key'] ? 'active' : '' }}"
                        href="{{ route('student.results', ['class' => $classOption['key'], 'search' => \App\Support\RecordSearch::term()]) }}"
                    >
                        <span class="fw-semibold d-block">{{ $classOption['label'] }}</span>
                        <span class="small">{{ $classOption['subject'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="results-summary-grid mb-4">
        <section class="result-card">
            <p class="result-label">Submitted</p>
            <div class="result-value">{{ $summary['submitted_assessments'] }}</div>
        </section>
        <section class="result-card">
            <p class="result-label">Passed</p>
            <div class="result-value">{{ $summary['passed_count'] }}</div>
        </section>
        <section class="result-card">
            <p class="result-label">Failed</p>
            <div class="result-value">{{ $summary['failed_count'] }}</div>
        </section>
    </div>

    <section class="results-panel">
        @if ($results->isNotEmpty())
            <div class="result-list row g-4">
                @foreach ($results as $result)
                    @php
                        $assessment = $result['assessment'];
                        $class = $result['class'];
                        $publishAssessment = $result['publish_assessment'];
                        $bestAttempt = $result['best_attempt'];
                        $scoreVisible = $result['score_visible'];
                        $canViewResult = $scoreVisible || $result['answer_visible'];
                    @endphp
                    <div class="col-xl-6">
                        <article class="assessment-result-card">
                            <div class="result-card-header">
                                <div>
                                    <h3 class="result-card-title">{{ $assessment?->title ?? 'Untitled Assessment' }}</h3>
                                    <p class="result-card-code">{{ $assessment?->subject?->subject_code ?? $class?->subject?->subject_code ?? 'No subject' }} - {{ $class?->class_name ?? 'Class removed' }}</p>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    @if (! $scoreVisible || ! $bestAttempt)
                                        <span class="badge text-bg-light rounded-1 d-block">Hidden</span>
                                    @elseif ($result['passed'])
                                        <span class="badge text-bg-success rounded-1 d-block">Passed</span>
                                    @else
                                        <span class="badge text-bg-danger rounded-1 d-block">Failed</span>
                                    @endif
                                </div>
                            </div>

                            <div class="result-card-body">
                                <div class="result-detail-list">
                                    @if ($scoreVisible && $bestAttempt)
                                        <span class="result-detail-badge">Best Score {{ $bestAttempt['score_text'] }} / {{ $result['max_score_text'] }}</span>
                                    @else
                                        <span class="result-detail-badge">Score Not Released</span>
                                    @endif
                                    <span class="result-detail-badge">{{ $result['attempt_count'] }} attempt{{ $result['attempt_count'] === 1 ? '' : 's' }}</span>
                                    @if ($bestAttempt && $bestAttempt['submitted_at'])
                                        <span class="result-detail-badge">Submitted {{ $bestAttempt['submitted_at']->format('M d, Y') }}</span>
                                    @else
                                        <span class="result-detail-badge">Submitted -</span>
                                    @endif
                                </div>

                                <p class="text-secondary mb-3">
                                    Instructor: {{ $class?->instructorProfile?->user?->displayName() ?? 'No instructor' }}
                                </p>

                                <p class="result-release-note">
                                    @if ($scoreVisible && $bestAttempt)
                                        {{ $bestAttempt['warning_count'] }} warning{{ $bestAttempt['warning_count'] === 1 ? '' : 's' }} recorded
                                    @else
                                        Results will appear when your instructor releases them.
                                    @endif
                                </p>

                                <div class="result-card-actions">
                                    @if ($canViewResult && $publishAssessment)
                                        <a
                                            class="btn btn-psu d-inline-flex align-items-center gap-2"
                                            href="{{ route('student.assessments.submitted', ['publishAssessment' => $publishAssessment, 'show_results' => 1]) }}"
                                            data-no-ajax="true"
                                        >
                                            <span class="material-symbols-outlined fs-5">visibility</span>
                                            View Results
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
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
