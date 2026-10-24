@extends('layouts.assessment')

@section('title', $assessment->title . ' | Assessment Attempt')
@section('attempt-label', 'Quiz Attempt #1')
@section('body_class', $publishAssessment->display_mode === \App\Models\PublishAssessment::DISPLAY_ONE_QUESTION ? 'one-question-attempt' : '')

@section('navbar-actions')
    <a class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-2" href="{{ route('student.assessments.take', $publishAssessment) }}">
        <span class="material-symbols-outlined fs-6">close</span>
        Exit
    </a>
@endsection

@push('styles')
    <x-page-style path="css/pages/student/assessments/attempt.css" />
@endpush

@section('content')
    @php
        $itemCount = $items->count();
        $isOneQuestionMode = $publishAssessment->display_mode === \App\Models\PublishAssessment::DISPLAY_ONE_QUESTION;
        $questionTimeLimitSeconds = $isOneQuestionMode ? (int) ($publishAssessment->question_time_limit_seconds ?? 0) : 0;
        $dueIso = $publishAssessment->due_at?->toIso8601String();
        $isMonitoringActive = $publishAssessment->prevent_copy_paste
            || $publishAssessment->detect_tab_switch
            || $publishAssessment->screenshot_protection;
        $enabledSecurities = collect([
            ['enabled' => $publishAssessment->prevent_copy_paste, 'icon' => 'content_paste_off', 'label' => 'No copy / paste'],
            ['enabled' => $publishAssessment->detect_tab_switch, 'icon' => 'tab', 'label' => 'Tab/floating monitor'],
            ['enabled' => $publishAssessment->screenshot_protection, 'icon' => 'screenshot_monitor', 'label' => 'Screenshot deterrent'],
            ['enabled' => $isOneQuestionMode, 'icon' => 'lock', 'label' => 'No backtracking (One-way)'],
        ])->where('enabled');
    @endphp

    <div class="security-warning-backdrop" id="securityWarningBackdrop" aria-hidden="true">
        <div class="security-warning-modal">
            <div class="security-warning-bar">
                <span id="securityWarningProgress"></span>
            </div>
            <div class="p-4">
                <div class="d-flex justify-content-end">
                    <button class="btn btn-sm btn-light border d-inline-flex align-items-center justify-content-center" id="closeSecurityWarning" type="button" aria-label="Close warning">
                        <span class="material-symbols-outlined fs-6">close</span>
                    </button>
                </div>
                <div class="text-center px-sm-3 pb-2">
                    <div class="security-warning-icon mx-auto mb-3">
                        <span class="material-symbols-outlined fs-1">warning</span>
                    </div>
                    <div class="brand-text h3 mb-2" id="securityWarningTitle" style="color: var(--psu-navy);">Security Warning</div>
                    <p class="text-secondary mb-3" id="securityWarningMessage">Restricted action detected. Stay on this assessment page.</p>
                    <span class="badge rounded-pill text-bg-warning px-3 py-2 mb-4" id="securityWarningCount">Warning {{ $submission->warning_count }} of {{ $warningLimit }}</span>
                    <button class="btn btn-psu w-100 d-inline-flex align-items-center justify-content-center gap-2" id="acknowledgeSecurityWarning" type="button">
                        <span class="material-symbols-outlined">check_circle</span>
                        I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($isOneQuestionMode && $itemCount > 1)
        <div class="modal fade" id="nextQuestionConfirmModal" tabindex="-1" aria-labelledby="nextQuestionConfirmTitle" aria-describedby="nextQuestionConfirmDescription" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-body p-4">
                        <div class="submit-confirm-icon mb-3" aria-hidden="true">
                            <span class="material-symbols-outlined fs-1">lock</span>
                        </div>
                        <h2 class="brand-text h4 mb-2" id="nextQuestionConfirmTitle" style="color: var(--psu-navy);">Go to Question <span id="nextQuestionNumber">2</span>?</h2>
                        <p class="text-secondary mb-3" id="nextQuestionConfirmDescription">Question <span id="leavingQuestionNumber">1</span> will be locked. You cannot return to it or change its answer.</p>
                        <div class="submit-confirm-summary rounded-2 small p-3 mb-3" id="nextQuestionAnswerStatus"></div>
                        @if ($questionTimeLimitSeconds > 0)
                            <p class="small text-danger mb-3">The question timer keeps running while this confirmation is open.</p>
                        @endif
                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <button class="btn btn-outline-secondary" id="stayOnQuestion" data-bs-dismiss="modal" type="button">Stay on Question</button>
                            <button class="btn btn-psu d-inline-flex align-items-center gap-1" id="confirmNextQuestion" type="button">
                                Confirm &amp; Next
                                <span class="material-symbols-outlined fs-5" aria-hidden="true">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="submitConfirmModal" tabindex="-1" aria-labelledby="submitConfirmTitle" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body p-4 p-lg-5 position-relative">
                    <button class="btn btn-sm btn-light border position-absolute top-0 end-0 m-3 d-inline-flex align-items-center justify-content-center" data-bs-dismiss="modal" type="button" aria-label="Close submit confirmation">
                        <span class="material-symbols-outlined fs-6">close</span>
                    </button>

                    <div class="text-center pt-2">
                        <div class="submit-confirm-icon mx-auto mb-3">
                            <span class="material-symbols-outlined fs-1">send</span>
                        </div>
                        <h2 class="brand-text h3 mb-2" id="submitConfirmTitle" style="color: var(--psu-navy);">Submit Assessment?</h2>
                        <p class="text-secondary mb-4" id="submitConfirmMessage">
                            Please confirm your submission. You cannot edit this attempt after submission.
                        </p>
                    </div>

                    <div class="submit-confirm-summary p-3 mb-4">
                        <div class="row g-3 text-center">
                            <div class="col-6">
                                <p class="small fw-bold text-secondary text-uppercase mb-1">Answered</p>
                                <p class="h3 fw-bold mb-0" style="color: var(--psu-navy);" id="submitConfirmAnswered">0</p>
                            </div>
                            <div class="col-6">
                                <p class="small fw-bold text-secondary text-uppercase mb-1">Total Items</p>
                                <p class="h3 fw-bold mb-0" style="color: var(--psu-navy);">{{ $itemCount }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <button class="btn btn-psu px-4 d-inline-flex align-items-center justify-content-center gap-2" id="confirmSubmitAssessment" type="button">
                            <span class="material-symbols-outlined fs-5">check_circle</span>
                            Submit Assessment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="status-card p-3 p-md-4 mb-4">
        <div class="row attempt-status-grid align-items-center">
            <div class="col-6 col-md-3 col-lg-2">
                <div class="stat-label mb-1">Progress</div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="stat-value" id="answeredCount">0</span>
                    <span class="stat-sub">/ {{ $itemCount }}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar" id="progressBar" style="width: 0%; background: var(--psu-navy);"></div>
                </div>
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <div class="stat-label mb-1">{{ $isOneQuestionMode ? 'Current Question' : 'Questions' }}</div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="stat-value" id="currentQuestion">{{ $isOneQuestionMode ? 1 : $itemCount }}</span>
                    <span class="stat-sub">/ {{ $itemCount }}</span>
                </div>
            </div>

            <div class="col-6 col-md-3 col-lg-3">
                <div class="stat-label mb-1">Time Remaining</div>
                <div class="stat-value text-danger" id="timeRemaining">--:--</div>
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <div class="stat-label mb-1">Warnings Used</div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="stat-value" id="warningCount">{{ $submission->warning_count }}</span>
                    <span class="stat-sub">/ {{ $warningLimit }}</span>
                </div>
            </div>

            <div class="col-12 col-lg-3 text-lg-end">
                <div @class(['monitoring-badge', 'monitoring-badge-inactive' => ! $isMonitoringActive])>
                    <span class="pulse-dot" aria-hidden="true"></span>
                    <span class="small">{{ $isMonitoringActive ? 'Monitoring Active' : 'Monitoring Off' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="alert bg-white security-alert d-flex align-items-start gap-3 mb-4 shadow-sm">
        <span class="material-symbols-outlined" style="color: var(--psu-navy);">info</span>
        <div class="small">
            <div>{{ $assessment->title }} is active. Stay on this page and avoid restricted actions while answering.</div>
            @if ($enabledSecurities->isNotEmpty())
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($enabledSecurities as $security)
                        <span class="badge text-bg-light border rounded-1 d-inline-flex align-items-center gap-1 px-2 py-2">
                            <span class="material-symbols-outlined fs-6">{{ $security['icon'] }}</span>
                            {{ $security['label'] }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($isOneQuestionMode)
        <div class="mb-4 {{ $publishAssessment->prevent_copy_paste ? 'no-select' : '' }}" data-copy-protected>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-bold text-secondary text-uppercase d-flex align-items-center gap-1">
                    <span class="material-symbols-outlined fs-6">linear_scale</span>
                    Question Progress
                </span>
                <span class="badge text-bg-warning border rounded-1 d-inline-flex align-items-center gap-1 small">
                    <span class="material-symbols-outlined" style="font-size: 14px;">lock</span> No Backtracking
                </span>
            </div>
            <div class="d-flex gap-2 overflow-auto pb-2">
                @foreach ($items as $item)
                    <div class="question-jump {{ $loop->first ? 'active' : '' }}" data-question-jump="{{ $loop->index }}">
                        {{ $loop->iteration }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <form id="assessmentAttemptForm" action="{{ route('student.assessments.submit', $publishAssessment) }}" method="POST">
        @csrf

        <div class="d-grid gap-4 {{ $publishAssessment->prevent_copy_paste ? 'no-select' : '' }} {{ $isOneQuestionMode ? 'one-question-mode' : '' }}" data-copy-protected>
            @foreach ($items as $item)
                <article class="question-card {{ $isOneQuestionMode && $loop->first ? 'active-question' : '' }}" id="questionCard{{ $loop->iteration }}" data-question-card data-question-index="{{ $loop->index }}">
                    <div class="question-expired-note mb-3">
                        Time is up for this question. Your saved answer is locked.
                    </div>

                    <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge rounded-pill px-3 py-2" style="background: var(--psu-navy);">Question {{ $loop->iteration }}</span>
                            <span class="badge bg-light text-primary border rounded-pill px-3 py-2">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span>
                            <span class="badge bg-light text-primary border rounded-pill px-3 py-2">{{ number_format((float) $item->points, 2) }} pts</span>
                        </div>
                        @if ($questionTimeLimitSeconds > 0)
                            <div class="question-timer">
                                <div class="small fw-bold text-danger text-nowrap">
                                    <span>Question Time:</span>
                                    <span data-question-time-remaining>--</span>
                                </div>
                                <div class="question-timer-track mt-2" aria-hidden="true">
                                    <div class="question-timer-fill" data-question-time-bar></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <h2 class="h4 brand-text mb-4" style="color: var(--psu-navy);">{{ $item->question_text }}</h2>

                    @if ($item->choices->isNotEmpty() && in_array($item->item_type, ['multiple_choice', 'true_false'], true))
                        <div class="d-grid gap-2 options-container">
                            @foreach ($item->choices as $choice)
                                <label class="option-label">
                                    <input class="form-check-input mt-0" name="answers[{{ $item->assessment_item_id }}]" type="radio" value="{{ $choice->assessment_item_choice_id }}">
                                    <span>
                                        @if ($item->item_type === 'multiple_choice')
                                            {{ chr(64 + $loop->iteration) }}.
                                        @endif
                                        {{ $choice->choice_text }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @elseif ($item->item_type === 'enumeration')
                        @php $enumCount = $item->choices->where('is_correct', true)->count() ?: 1; @endphp
                        <p class="small fw-semibold text-secondary mb-2">
                            Write {{ $enumCount }} answer{{ $enumCount === 1 ? '' : 's' }}, one per box.
                            @if ($item->order_sensitive)
                                <span class="badge text-bg-light border rounded-1 ms-1">In order</span>
                            @endif
                        </p>
                        <div class="d-grid gap-2">
                            @for ($ei = 0; $ei < $enumCount; $ei++)
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold text-secondary" style="min-width:1.5rem;">{{ $ei + 1 }}.</span>
                                    <input class="form-control"
                                        name="answers[{{ $item->assessment_item_id }}][]"
                                        type="text"
                                        placeholder="Answer {{ $ei + 1 }}"
                                        autocomplete="off">
                                </div>
                            @endfor
                        </div>
                    @elseif ($item->item_type === 'essay')
                        <textarea class="form-control" name="answers[{{ $item->assessment_item_id }}]" rows="6" placeholder="Type your answer"></textarea>
                    @else
                        <input class="form-control form-control-lg" name="answers[{{ $item->assessment_item_id }}]" type="text" placeholder="Type your answer">
                    @endif
                    @if ($isOneQuestionMode)
                        <div class="question-actions d-flex justify-content-end mt-4">
                            @if ($loop->last)
                                <button class="btn btn-psu d-inline-flex align-items-center justify-content-center gap-2" id="submitAssessmentBtn" type="submit">
                                    <span class="material-symbols-outlined fs-5" aria-hidden="true">send</span>
                                    Submit Assessment
                                </button>
                            @else
                                <button class="btn btn-psu d-inline-flex align-items-center justify-content-center gap-2" data-next-question type="button">
                                    Next Question
                                    <span class="material-symbols-outlined fs-5" aria-hidden="true">chevron_right</span>
                                </button>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </form>

    @if (! $isOneQuestionMode)
        <div class="sticky-action-bar py-3">
            <div class="container d-flex justify-content-end">
                <button class="btn btn-psu shadow-sm d-inline-flex align-items-center justify-content-center gap-2" form="assessmentAttemptForm" type="submit">
                    <span class="material-symbols-outlined">send</span>
                    Submit Assessment
                </button>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @include('student.assessments.assessment-attempt-page-code')
@endpush
