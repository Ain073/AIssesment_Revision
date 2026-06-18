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

        .one-question-mode .question-card {
            display: none;
        }

        .one-question-mode .question-card.active-question {
            display: block;
        }

        .option-label {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            width: 100%;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
            padding: 0.9rem 1rem;
            cursor: pointer;
            transition: border-color 0.18s ease, background-color 0.18s ease;
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

        .screenshot-watermark {
            pointer-events: none;
            position: fixed;
            inset: 0;
            z-index: 1020;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 3rem;
            padding: 5rem 2rem;
            opacity: 0.075;
            color: var(--psu-navy);
            font-weight: 800;
            text-transform: uppercase;
            transform: rotate(-18deg);
        }

        .no-select {
            user-select: none;
        }

        @media (max-width: 575.98px) {
            .stat-value {
                font-size: 2.1rem;
            }

            .question-card {
                padding: 1rem;
            }

            .sticky-action-bar .container {
                gap: 0.75rem;
            }

            .sticky-action-bar .btn {
                width: 100%;
                justify-content: center;
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
            ['enabled' => $classAssessment->detect_tab_switch, 'icon' => 'tab', 'label' => 'Tab switch monitoring'],
            ['enabled' => $classAssessment->screenshot_protection, 'icon' => 'screenshot_monitor', 'label' => 'Screenshot deterrent'],
        ])->where('enabled');
    @endphp

    @if ($classAssessment->screenshot_protection)
        <div class="screenshot-watermark" aria-hidden="true">
            @for ($i = 0; $i < 18; $i++)
                <span>{{ $studentProfile->student_number ?? $user->email }} - {{ now()->format('Y-m-d H:i') }}</span>
            @endfor
        </div>
    @endif

    <div class="status-card p-3 p-md-4 mb-4">
        <div class="row g-4 align-items-center">
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
                    <span class="stat-value" id="warningCount">0</span>
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
        <div class="d-flex gap-2 overflow-auto pb-3 mb-4 {{ $classAssessment->prevent_copy_paste ? 'no-select' : '' }}">
            @foreach ($items as $item)
                <button class="question-jump {{ $loop->first ? 'active' : '' }}" data-question-jump="{{ $loop->index }}" type="button">
                    {{ $loop->iteration }}
                </button>
            @endforeach
        </div>
    @endif

    <form id="assessmentAttemptForm" action="{{ route('student.assessments.submit', $classAssessment) }}" method="POST">
        @csrf
        <input id="warningsUsedInput" name="warnings_used" type="hidden" value="0">

        <div class="d-grid gap-4 {{ $classAssessment->prevent_copy_paste ? 'no-select' : '' }} {{ $isOneQuestionMode ? 'one-question-mode' : '' }}">
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
    <script>
        const cards = Array.from(document.querySelectorAll('[data-question-card]'));
        const jumps = Array.from(document.querySelectorAll('[data-question-jump]'));
        const warningLimit = Number(@json($warningLimit));
        const preventCopyPaste = Boolean(@json($classAssessment->prevent_copy_paste));
        const detectTabSwitch = Boolean(@json($classAssessment->detect_tab_switch));
        const screenshotProtection = Boolean(@json($classAssessment->screenshot_protection));
        const oneQuestionMode = Boolean(@json($isOneQuestionMode));
        const dueAt = @json($dueIso);
        const countdownTarget = dueAt ? new Date(dueAt).getTime() : Date.now() + (60 * 60 * 1000);
        let currentIndex = 0;
        let warnings = 0;

        const setCurrentQuestion = (index) => {
            if (! oneQuestionMode || cards.length === 0) {
                return;
            }

            currentIndex = Math.min(Math.max(index, 0), Math.max(cards.length - 1, 0));
            document.getElementById('currentQuestion').textContent = String(currentIndex + 1);

            jumps.forEach((jump, jumpIndex) => {
                jump.classList.toggle('active', jumpIndex === currentIndex);
            });

            cards.forEach((card, cardIndex) => {
                card.classList.toggle('active-question', cardIndex === currentIndex);
            });

            cards[currentIndex]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('previousQuestion').disabled = currentIndex === 0;
            document.getElementById('nextQuestion').innerHTML = currentIndex === cards.length - 1
                ? 'Review Answers <span class="material-symbols-outlined">checklist</span>'
                : 'Next Question <span class="material-symbols-outlined">chevron_right</span>';
        };

        const answeredQuestions = () => {
            return cards.filter((card) => {
                const checked = card.querySelector('input[type="radio"]:checked');
                const textField = card.querySelector('textarea, input[type="text"]');

                return Boolean(checked || (textField && textField.value.trim()));
            });
        };

        const updateProgress = () => {
            const answered = answeredQuestions();
            const percent = cards.length ? Math.round((answered.length / cards.length) * 100) : 0;

            document.getElementById('answeredCount').textContent = String(answered.length);
            document.getElementById('progressBar').style.width = `${percent}%`;

            jumps.forEach((jump, index) => {
                jump.classList.toggle('answered', Boolean(answered.find((card) => Number(card.dataset.questionIndex) === index)));
            });
        };

        const recordWarning = () => {
            if (warningLimit === 0 || warnings >= warningLimit) {
                return;
            }

            warnings += 1;
            document.getElementById('warningCount').textContent = String(warnings);
            document.getElementById('warningsUsedInput').value = String(warnings);
        };

        const updateCountdown = () => {
            const remaining = Math.max(0, countdownTarget - Date.now());
            const totalSeconds = Math.floor(remaining / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            const display = hours > 0
                ? `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
                : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

            document.getElementById('timeRemaining').textContent = display;
        };

        document.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                const container = radio.closest('.options-container');
                container?.querySelectorAll('.option-label').forEach((label) => label.classList.remove('selected'));
                radio.closest('.option-label')?.classList.add('selected');
                updateProgress();
            });
        });

        document.querySelectorAll('textarea, input[type="text"]').forEach((field) => {
            field.addEventListener('input', updateProgress);
        });

        if (oneQuestionMode) {
            jumps.forEach((jump) => {
                jump.addEventListener('click', () => setCurrentQuestion(Number(jump.dataset.questionJump)));
            });

            document.getElementById('previousQuestion')?.addEventListener('click', () => setCurrentQuestion(currentIndex - 1));
            document.getElementById('nextQuestion')?.addEventListener('click', () => setCurrentQuestion(currentIndex + 1));
        }

        if (preventCopyPaste) {
            ['copy', 'cut', 'paste', 'contextmenu'].forEach((eventName) => {
                document.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    recordWarning();
                });
            });
        }

        document.addEventListener('keydown', (event) => {
            const key = event.key.toLowerCase();
            const copyPasteCombo = preventCopyPaste && (event.ctrlKey || event.metaKey) && ['c', 'x', 'v'].includes(key);
            const screenshotCombo = screenshotProtection && (
                event.key === 'PrintScreen'
                || ((event.ctrlKey || event.metaKey) && key === 'p')
            );

            if (copyPasteCombo || screenshotCombo) {
                event.preventDefault();
                recordWarning();
            }
        });

        if (detectTabSwitch) {
            window.addEventListener('blur', recordWarning);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    recordWarning();
                }
            });
        }

        if (oneQuestionMode) {
            setCurrentQuestion(0);
        }

        document.getElementById('assessmentAttemptForm').addEventListener('submit', (event) => {
            updateProgress();

            const answered = answeredQuestions().length;
            const message = answered < cards.length
                ? `You answered ${answered} of ${cards.length} questions. Submit anyway?`
                : 'Submit your assessment now?';

            if (! window.confirm(message)) {
                event.preventDefault();
            }
        });
        updateProgress();
        updateCountdown();
        window.setInterval(updateCountdown, 1000);
    </script>
@endpush
