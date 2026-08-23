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
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        }

        .assessment-result-card {
            background: #fff;
            border: 1px solid #d8e1f4;
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
            display: flex;
            flex-direction: column;
            min-height: 245px;
            padding: 1rem;
        }

        .result-card-top,
        .result-card-actions {
            align-items: flex-start;
            display: flex;
            gap: 0.75rem;
            justify-content: space-between;
        }

        .result-card-title {
            color: var(--psu-navy);
            font-family: var(--psu-heading-font);
            font-size: clamp(1.35rem, 2vw, 1.75rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 0.2rem;
            overflow-wrap: anywhere;
        }

        .result-card-code {
            color: #5f6f87;
            font-size: 0.86rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
        }

        .result-card-meta {
            color: #66758b;
            display: flex;
            flex-wrap: wrap;
            font-size: 0.9rem;
            gap: 0.4rem 0.6rem;
        }

        .result-detail-list {
            display: grid;
            gap: 0.75rem 1rem;
            grid-template-columns: repeat(auto-fit, minmax(86px, 1fr));
            margin-top: 1rem;
            padding-block: 0.3rem 0.85rem;
        }

        .result-detail-pill {
            min-width: 0;
        }

        .result-detail-label {
            color: #6b7689;
            display: block;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            margin-bottom: 0.2rem;
            text-transform: uppercase;
        }

        .result-detail-value {
            color: var(--psu-navy);
            display: block;
            font-size: 0.98rem;
            font-weight: 800;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .result-card-actions {
            align-items: center;
            border-top: 1px solid #e6edf8;
            gap: 0.75rem;
            margin-top: auto;
            padding-top: 0.9rem;
        }

        .result-release-note {
            color: #6b7689;
            font-size: 0.86rem;
            margin-bottom: 0;
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
                padding: 1rem;
            }

            .result-card-top {
                align-items: flex-start;
            }

            .result-card-meta {
                font-size: 0.9rem;
            }

            .result-detail-list {
                gap: 0.45rem;
                grid-template-columns: repeat(3, minmax(0, 1fr));
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
            <div class="result-list">
                @foreach ($results as $result)
                    @php
                        $assessment = $result['assessment'];
                        $class = $result['class'];
                        $classAssessment = $result['class_assessment'];
                        $bestAttempt = $result['best_attempt'];
                        $scoreVisible = $result['score_visible'];
                        $canViewResult = $scoreVisible || $result['answer_visible'];
                    @endphp
                    <article class="assessment-result-card">
                        <div class="result-card-top">
                            <div>
                                <p class="result-card-code">{{ $assessment?->subject?->subject_code ?? $class?->subject?->subject_code ?? 'No subject' }}</p>
                                <h3 class="result-card-title">{{ $assessment?->title ?? 'Untitled Assessment' }}</h3>
                                <div class="result-card-meta">
                                    <span>{{ $class?->class_name ?? 'Class removed' }}</span>
                                    <span aria-hidden="true">|</span>
                                    <span>{{ $class?->instructorProfile?->user?->displayName() ?? 'No instructor' }}</span>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                @if (! $scoreVisible || ! $bestAttempt)
                                    <span class="badge text-bg-secondary rounded-1 d-block">Hidden</span>
                                @elseif ($result['passed'])
                                    <span class="badge text-bg-success rounded-1 d-block">Passed</span>
                                @else
                                    <span class="badge text-bg-danger rounded-1 d-block">Failed</span>
                                @endif
                            </div>
                        </div>

                        <div class="result-detail-list">
                            <div class="result-detail-pill">
                                <span class="result-detail-label">Best Score</span>
                                @if ($scoreVisible && $bestAttempt)
                                    <span class="result-detail-value">{{ $bestAttempt['score_text'] }} / {{ $result['max_score_text'] }}</span>
                                @else
                                    <span class="result-detail-value text-secondary">Not released</span>
                                @endif
                            </div>
                            <div class="result-detail-pill">
                                <span class="result-detail-label">Attempts</span>
                                <span class="result-detail-value">{{ $result['attempt_count'] }}</span>
                            </div>
                            <div class="result-detail-pill">
                                <span class="result-detail-label">Submitted</span>
                                @if ($bestAttempt && $bestAttempt['submitted_at'])
                                    <span class="result-detail-value">{{ $bestAttempt['submitted_at']->format('M d, Y') }}</span>
                                @else
                                    <span class="result-detail-value text-secondary">-</span>
                                @endif
                            </div>
                        </div>

                        <div class="result-card-actions">
                            <p class="result-release-note">
                                @if ($scoreVisible && $bestAttempt)
                                    {{ $bestAttempt['warning_count'] }} warning{{ $bestAttempt['warning_count'] === 1 ? '' : 's' }} recorded
                                @else
                                    Results will appear when your instructor releases them.
                                @endif
                            </p>
                            @if ($canViewResult && $classAssessment)
                                <a
                                    class="btn btn-outline-primary d-inline-flex align-items-center gap-2"
                                    href="{{ route('student.assessments.submitted', ['classAssessment' => $classAssessment, 'show_results' => 1]) }}"
                                    data-no-ajax="true"
                                >
                                    <span class="material-symbols-outlined fs-5">visibility</span>
                                    View Results
                                </a>
                            @endif
                        </div>
                    </article>
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
