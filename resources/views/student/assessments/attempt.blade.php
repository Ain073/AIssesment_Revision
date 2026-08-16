@extends('layouts.assessment')

@section('title', $assessment->title . ' | Assessment Attempt')
@section('attempt-label', 'Quiz Attempt #1')

@section('navbar-actions')
    <a class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-2" href="{{ route('student.assessments.take', $classAssessment) }}">
        <span class="material-symbols-outlined fs-6">close</span>
        Exit
    </a>
@endsection

@push('styles')
    <style>
        .status-card,
        .question-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .status-card {
            background: #eff4ff;
        }

        .attempt-status-grid {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 1rem;
        }

        .stat-label {
            color: var(--psu-muted);
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .stat-value {
            color: var(--psu-navy);
            font-family: "Oswald", sans-serif;
            font-size: 2.7rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-sub {
            color: var(--psu-muted);
            font-size: 1rem;
            font-weight: 700;
        }

        .monitoring-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            border-left: 4px solid var(--psu-gold);
            border-radius: 0.5rem;
            background: var(--psu-navy);
            color: #fff;
            padding: 0.75rem 1rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .pulse-dot {
            position: relative;
            width: 0.7rem;
            height: 0.7rem;
            border-radius: 50%;
            background: var(--psu-gold);
        }

        .pulse-dot::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: var(--psu-gold);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            from {
                opacity: 0.75;
                transform: scale(1);
            }

            to {
                opacity: 0;
                transform: scale(2.5);
            }
        }

        .question-jump {
            width: 2.85rem;
            height: 2.85rem;
            flex: 0 0 auto;
            border-radius: 50%;
            border: 2px solid var(--psu-line);
            background: #fff;
            color: var(--psu-navy);
            font-weight: 800;
        }

        .question-jump.active {
            border-color: var(--psu-navy);
            background: var(--psu-navy);
            color: var(--psu-gold);
        }

        .question-jump.answered {
            border-color: var(--psu-navy-2);
        }

        .question-card {
            padding: 1.5rem;
            scroll-margin-top: 5.5rem;
        }

        .question-card .form-control {
            font-size: 1rem;
            line-height: 1.5;
        }

        .one-question-mode .question-card {
            display: none;
        }

        .one-question-mode .question-card.active-question {
            display: block;
        }

        .option-label {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            width: 100%;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
            padding: 0.9rem 1rem;
            cursor: pointer;
            transition: border-color 0.18s ease, background-color 0.18s ease;
        }

        .option-label .form-check-input {
            flex: 0 0 auto;
            margin-top: 0.2rem !important;
        }

        .option-label span {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .option-label:hover,
        .option-label.selected {
            border-color: var(--psu-navy-2);
            background: #edf3ff;
        }

        .form-check-input:checked {
            border-color: var(--psu-navy);
            background-color: var(--psu-navy);
        }

        .sticky-action-bar {
            position: fixed;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 1030;
            border-top: 1px solid var(--psu-line);
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 -12px 28px rgba(0, 26, 112, 0.08);
        }

        .security-alert {
            border-left: 4px solid var(--psu-navy);
        }

        .security-warning-backdrop {
            position: fixed;
            inset: 0;
            z-index: 2040;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(0, 26, 112, 0.2);
            backdrop-filter: blur(3px);
        }

        .security-warning-backdrop.show {
            display: flex;
            animation: warningFadeIn 0.18s ease-out;
        }

        .security-warning-modal {
            width: min(100%, 440px);
            overflow: hidden;
            border: 1px solid rgba(255, 210, 0, 0.7);
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 24px 60px rgba(0, 26, 112, 0.26);
            animation: warningPop 0.2s ease-out, warningShake 0.32s ease-in-out 0.2s;
        }

        .security-warning-bar {
            height: 0.35rem;
            background: #eef3ff;
        }

        .security-warning-bar span {
            display: block;
            width: 0%;
            height: 100%;
            background: var(--psu-gold);
            transition: width 0.2s ease;
        }

        .security-warning-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3.35rem;
            height: 3.35rem;
            border-radius: 50%;
            background: #fff4ba;
            color: var(--psu-navy);
            box-shadow: 0 0 0 0 rgba(255, 210, 0, 0.7);
            animation: warningPulse 1.2s infinite;
        }

        .submit-confirm-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy);
            border: 1px solid rgba(255, 218, 39, 0.75);
        }

        .submit-confirm-summary {
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #f8faff;
        }

        @keyframes warningFadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes warningPop {
            from {
                opacity: 0;
                transform: scale(0.92) translateY(0.5rem);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes warningShake {
            0%, 100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-0.35rem);
            }

            50% {
                transform: translateX(0.35rem);
            }

            75% {
                transform: translateX(-0.18rem);
            }
        }

        @keyframes warningPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 210, 0, 0.7);
            }

            70% {
                box-shadow: 0 0 0 0.75rem rgba(255, 210, 0, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(255, 210, 0, 0);
            }
        }

        .no-select {
            user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
        }

        .no-select *:not(input):not(textarea) {
            user-select: none;
            -webkit-user-select: none;
            -ms-user-select: none;
        }

        .no-select input,
        .no-select textarea {
            user-select: text;
            -webkit-user-select: text;
            -ms-user-select: text;
            touch-action: manipulation;
        }

        .no-select img {
            -webkit-user-drag: none;
            user-drag: none;
        }

        @media (max-width: 575.98px) {
            .status-card {
                padding: 0.85rem !important;
            }

            .attempt-status-grid {
                --bs-gutter-x: 0.65rem;
                --bs-gutter-y: 0.65rem;
            }

            .stat-label {
                font-size: 0.62rem;
            }

            .stat-value {
                font-size: 1.65rem;
            }

            .stat-sub {
                font-size: 0.78rem;
            }

            .monitoring-badge {
                width: 100%;
                justify-content: center;
                padding: 0.6rem 0.75rem;
                font-size: 0.76rem;
            }

            .question-card {
                padding: 0.85rem;
                border-radius: 0.45rem;
                scroll-margin-top: 4.75rem;
            }

            .question-card .mb-3.d-flex {
                gap: 0.45rem !important;
                margin-bottom: 0.85rem !important;
            }

            .question-card h2 {
                margin-bottom: 1rem !important;
                font-size: 1.02rem;
                line-height: 1.35;
                overflow-wrap: anywhere;
            }

            .question-card .badge {
                padding: 0.35rem 0.55rem !important;
                font-size: 0.66rem;
                white-space: normal;
            }

            .option-label {
                gap: 0.6rem;
                min-height: 3rem;
                padding: 0.7rem 0.75rem;
                line-height: 1.35;
            }

            .question-card .form-control,
            .question-card .form-control-lg {
                min-height: 2.85rem;
                font-size: 1rem;
            }

            .question-card textarea.form-control {
                min-height: 8rem;
            }

            .sticky-action-bar .container {
                gap: 0.75rem;
            }

            .sticky-action-bar .btn {
                width: 100%;
                justify-content: center;
            }

            .security-alert {
                gap: 0.65rem !important;
                margin-bottom: 1rem !important;
                padding: 0.8rem !important;
                font-size: 0.82rem;
            }

            .security-alert .material-symbols-outlined {
                font-size: 1.25rem;
            }

            .security-alert .badge {
                max-width: 100%;
                padding: 0.35rem 0.45rem !important;
                font-size: 0.64rem;
                line-height: 1.2;
                white-space: normal;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $itemCount = $items->count();
        $isOneQuestionMode = $classAssessment->display_mode === \App\Models\ClassAssessment::DISPLAY_ONE_QUESTION;
        $dueIso = $classAssessment->due_at?->toIso8601String();
        $enabledSecurities = collect([
            ['enabled' => $classAssessment->prevent_copy_paste, 'icon' => 'content_paste_off', 'label' => 'No copy / paste'],
            ['enabled' => $classAssessment->detect_tab_switch, 'icon' => 'tab', 'label' => 'Tab/floating monitor'],
            ['enabled' => $classAssessment->screenshot_protection, 'icon' => 'screenshot_monitor', 'label' => 'Screenshot deterrent'],
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

    <div class="modal fade" id="submitConfirmModal" tabindex="-1" aria-labelledby="submitConfirmTitle" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body p-4 p-lg-5">
                    <div class="text-center">
                        <div class="submit-confirm-icon mx-auto mb-3">
                            <span class="material-symbols-outlined fs-1">send</span>
                        </div>
                        <h2 class="brand-text h3 mb-2" id="submitConfirmTitle" style="color: var(--psu-navy);">Submit Assessment?</h2>
                        <p class="text-secondary mb-4" id="submitConfirmMessage">
                            Review your answers before submitting. You cannot edit this attempt after submission.
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

                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <button class="btn btn-outline-secondary px-4" id="cancelSubmitConfirm" data-bs-dismiss="modal" type="button">Review Answers</button>
                        <button class="btn btn-psu px-4 d-inline-flex align-items-center justify-content-center gap-2" id="confirmSubmitAssessment" type="button">
                            <span class="material-symbols-outlined fs-5">check_circle</span>
                            Submit Now
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
                <div class="monitoring-badge">
                    <span class="pulse-dot"></span>
                    <span class="small">Monitoring Active</span>
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
        <div class="d-flex gap-2 overflow-auto pb-3 mb-4 {{ $classAssessment->prevent_copy_paste ? 'no-select' : '' }}" data-copy-protected>
            @foreach ($items as $item)
                <button class="question-jump {{ $loop->first ? 'active' : '' }}" data-question-jump="{{ $loop->index }}" type="button">
                    {{ $loop->iteration }}
                </button>
            @endforeach
        </div>
    @endif

    <form id="assessmentAttemptForm" action="{{ route('student.assessments.submit', $classAssessment) }}" method="POST">
        @csrf

        <div class="d-grid gap-4 {{ $classAssessment->prevent_copy_paste ? 'no-select' : '' }} {{ $isOneQuestionMode ? 'one-question-mode' : '' }}" data-copy-protected>
            @foreach ($items as $item)
                <article class="question-card {{ $isOneQuestionMode && $loop->first ? 'active-question' : '' }}" id="questionCard{{ $loop->iteration }}" data-question-card data-question-index="{{ $loop->index }}">
                    <div class="mb-3 d-flex flex-wrap gap-2">
                        <span class="badge rounded-pill px-3 py-2" style="background: var(--psu-navy);">Question {{ $loop->iteration }}</span>
                        <span class="badge bg-light text-primary border rounded-pill px-3 py-2">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span>
                        <span class="badge bg-light text-primary border rounded-pill px-3 py-2">{{ number_format((float) $item->points, 2) }} pts</span>
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
                    @elseif ($item->item_type === 'essay')
                        <textarea class="form-control" name="answers[{{ $item->assessment_item_id }}]" rows="6" placeholder="Type your answer"></textarea>
                    @else
                        <input class="form-control form-control-lg" name="answers[{{ $item->assessment_item_id }}]" type="text" placeholder="Type your answer">
                    @endif
                </article>
            @endforeach
        </div>
    </form>

    @if ($isOneQuestionMode)
        <div class="sticky-action-bar py-3">
            <div class="container d-flex flex-column flex-sm-row justify-content-between gap-2">
                <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" id="previousQuestion" type="button">
                    <span class="material-symbols-outlined">chevron_left</span>
                    Previous
                </button>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <button class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2" id="nextQuestion" type="button">
                        Next Question
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>
                    <button class="btn btn-psu shadow-sm d-inline-flex align-items-center justify-content-center gap-2" form="assessmentAttemptForm" type="submit">
                        <span class="material-symbols-outlined">send</span>
                        Submit Assessment
                    </button>
                </div>
            </div>
        </div>
    @else
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
