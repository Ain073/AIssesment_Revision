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
            min-width: 0;
            padding: 1.1rem;
        }

        .results-summary-grid {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
            background: transparent;
            border: 0;
            box-shadow: none;
            overflow: hidden;
        }

        .result-list {
            --bs-gutter-x: 1.5rem;
            --bs-gutter-y: 1.75rem;
        }

        .assessment-result-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
            display: flex;
            flex-direction: column;
            min-height: 100%;
            overflow: hidden;
        }

        .result-card-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: 1rem 1.5rem;
        }

        .result-card-title {
            color: #fff;
            font-family: var(--psu-heading-font);
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 0.25rem;
            overflow-wrap: anywhere;
        }

        .result-card-code {
            color: rgba(255, 255, 255, 0.68);
            font-size: 0.86rem;
            font-weight: 800;
            margin-bottom: 0;
        }

        .result-card-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            padding: 1.5rem;
        }

        .result-detail-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .result-detail-badge {
            background: #fff;
            border: 1px solid #d8e1f4;
            border-radius: 0.25rem;
            color: #001a70;
            display: inline-flex;
            font-size: 0.86rem;
            font-weight: 800;
            line-height: 1;
            padding: 0.38rem 0.55rem;
        }

        .result-card-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: auto;
        }

        .result-card-actions .btn {
            flex-shrink: 0;
        }

        .result-release-note {
            color: #6b7689;
            font-size: 1rem;
            margin-bottom: 1rem;
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
            .results-summary-grid {
                gap: 0.5rem;
            }

            .result-card {
                min-height: 86px;
                padding: 0.75rem 0.65rem;
            }

            .result-label {
                font-size: 0.62rem;
                line-height: 1.2;
                margin-bottom: 0.35rem;
            }

            .result-value {
                font-size: 1.45rem;
            }

            .class-filter-button {
                min-width: 0;
                width: 100%;
            }

            .class-filter-menu {
                width: 100%;
            }

            .assessment-result-card {
                min-height: 0;
            }

            .result-card-header {
                align-items: flex-start;
                padding: 1rem;
            }

            .result-card-body {
                padding: 1rem;
            }

            .result-detail-list {
                gap: 0.45rem;
            }

            .result-card-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .result-card-actions .btn {
                justify-content: center;
                width: 100%;
            }
        }

        @media (max-width: 420px) {
            .result-card {
                padding-inline: 0.5rem;
            }

            .result-label {
                font-size: 0.58rem;
            }

            .result-value {
                font-size: 1.35rem;
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
