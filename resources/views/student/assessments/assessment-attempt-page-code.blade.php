<script>
    const cards = Array.from(document.querySelectorAll('[data-question-card]'));
    const jumps = Array.from(document.querySelectorAll('[data-question-jump]'));
    const warningLimit = Number(@json($warningLimit));
    const preventCopyPaste = Boolean(@json($publishAssessment->prevent_copy_paste));
    const detectTabSwitch = Boolean(@json($publishAssessment->detect_tab_switch));
    const screenshotProtection = Boolean(@json($publishAssessment->screenshot_protection));
    const oneQuestionMode = Boolean(@json($isOneQuestionMode));
    const securityEventUrl = @json(route('student.assessments.security-events.store', $publishAssessment));
    const dueAt = @json($dueIso);
    const countdownTarget = dueAt ? new Date(dueAt).getTime() : Date.now() + (60 * 60 * 1000);
    let currentIndex = 0;
    let warnings = Number(@json($submission->warning_count));
    let isAutoSubmitting = false;
    let isFinalSubmitting = false;
    let isSubmitConfirmOpen = false;
    const lastSecurityIncidentByType = new Map();
    const textAnswerSelector = 'input[type="text"], textarea';
    const isTextAnswerField = (element = document.activeElement) => Boolean(element?.matches?.(textAnswerSelector));

    const closeSecurityWarning = () => {
        if (isAutoSubmitting) {
            return;
        }

        document.getElementById('securityWarningBackdrop')?.classList.remove('show');
    };

    const syncWarningDisplay = () => {
        const pageWarningCount = document.getElementById('warningCount');
        const warningCount = document.getElementById('securityWarningCount');
        const warningProgress = document.getElementById('securityWarningProgress');

        if (pageWarningCount) {
            pageWarningCount.textContent = String(warnings);
        }

        if (warningCount) {
            warningCount.textContent = `Warning ${warnings} of ${warningLimit}`;
        }

        if (warningProgress) {
            warningProgress.style.width = warningLimit > 0 ? `${Math.min((warnings / warningLimit) * 100, 100)}%` : '0%';
        }
    };

    const showSecurityWarning = (
        title = 'Security Warning',
        message = 'Restricted action detected. Stay on this assessment page.',
        locked = false
    ) => {
        const backdrop = document.getElementById('securityWarningBackdrop');
        const warningTitle = document.getElementById('securityWarningTitle');
        const warningMessage = document.getElementById('securityWarningMessage');
        const acknowledgeButton = document.getElementById('acknowledgeSecurityWarning');
        const closeButton = document.getElementById('closeSecurityWarning');

        if (! backdrop) {
            return;
        }

        warningTitle.textContent = title;
        warningMessage.textContent = message;
        syncWarningDisplay();
        acknowledgeButton.disabled = locked;
        closeButton.disabled = locked;
        acknowledgeButton.innerHTML = locked
            ? '<span class="spinner-border spinner-border-sm"></span> Submitting...'
            : '<span class="material-symbols-outlined">check_circle</span> I Understand';

        backdrop.classList.remove('show');
        window.setTimeout(() => {
            backdrop.classList.add('show');
        }, 10);
    };

    const applyLocalWarning = (eventType) => {
        if (warningLimit > 0 && warnings < warningLimit) {
            warnings += 1;
        }

        syncWarningDisplay();
        showSecurityWarning(
            'Security Warning',
            securityMessages[eventType] || 'Restricted action detected. Stay on this assessment page.'
        );
    };

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
            ? 'Last Question <span class="material-symbols-outlined">checklist</span>'
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

    const autoSubmitAssessment = () => {
        if (isAutoSubmitting) {
            return;
        }

        isAutoSubmitting = true;
        showSecurityWarning(
            'Warning Limit Reached',
            'The warning limit was reached. Your assessment will be submitted automatically.',
            true
        );

        window.setTimeout(() => {
            document.getElementById('assessmentAttemptForm')?.requestSubmit();
        }, 1200);
    };

    const securityMessages = {
        copy_attempt: 'Copying assessment content is restricted.',
        cut_attempt: 'Cutting assessment content is restricted.',
        paste_attempt: 'Pasting content into this assessment is restricted.',
        context_menu_attempt: 'The context menu is restricted during this assessment.',
        tab_hidden: 'Leaving or hiding the assessment tab was detected.',
        window_blur: 'The assessment window lost focus.',
        floating_window: 'Floating window or split-screen mode was detected.',
        print_shortcut: 'Printing assessment content is restricted.',
        screenshot_shortcut: 'A screenshot shortcut was detected.',
    };

    const eventIdentifier = () => {
        if (window.crypto?.randomUUID) {
            return window.crypto.randomUUID();
        }

        return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    };

    const recordWarning = async (eventType) => {
        const now = Date.now();
        const lastIncidentAt = Number(lastSecurityIncidentByType.get(eventType) || 0);

        if (
            isAutoSubmitting
            || isFinalSubmitting
            || isSubmitConfirmOpen
            || now - lastIncidentAt < 1200
        ) {
            return;
        }

        lastSecurityIncidentByType.set(eventType, now);
        applyLocalWarning(eventType);

        try {
            const response = await fetch(securityEventUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('#assessmentAttemptForm input[name="_token"]')?.value || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    event_uuid: eventIdentifier(),
                    event_type: eventType,
                }),
                credentials: 'same-origin',
                keepalive: true,
            });

            if (! response.ok) {
                throw new Error('Security event could not be recorded.');
            }

            const result = await response.json();
            warnings = Number(result.warning_count || 0);
            syncWarningDisplay();

            if (result.should_auto_submit) {
                autoSubmitAssessment();
            }
        } catch (error) {
            console.warn('Security event recording failed.', error);
        }
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

    document.getElementById('acknowledgeSecurityWarning')?.addEventListener('click', closeSecurityWarning);
    document.getElementById('closeSecurityWarning')?.addEventListener('click', closeSecurityWarning);

    if (oneQuestionMode) {
        jumps.forEach((jump) => {
            jump.addEventListener('click', () => setCurrentQuestion(Number(jump.dataset.questionJump)));
        });

        document.getElementById('previousQuestion')?.addEventListener('click', () => setCurrentQuestion(currentIndex - 1));
        document.getElementById('nextQuestion')?.addEventListener('click', () => setCurrentQuestion(currentIndex + 1));
    }

    if (preventCopyPaste) {
        const restrictedEvents = {
            copy: 'copy_attempt',
            cut: 'cut_attempt',
            paste: 'paste_attempt',
            contextmenu: 'context_menu_attempt',
        };
        const isAnswerField = (element) => isTextAnswerField(element);

        Object.entries(restrictedEvents).forEach(([eventName, eventType]) => {
            document.addEventListener(eventName, (event) => {
                if (isAnswerField(event.target)) {
                    return;
                }

                event.preventDefault();
                recordWarning(eventType);
            });
        });

        document.querySelectorAll('[data-copy-protected]').forEach((section) => {
            section.addEventListener('selectstart', (event) => {
                if (isAnswerField(event.target)) {
                    return;
                }

                event.preventDefault();
                window.getSelection()?.removeAllRanges();
            });

            section.addEventListener('dragstart', (event) => {
                if (isAnswerField(event.target)) {
                    return;
                }

                event.preventDefault();
            });

            section.addEventListener('mouseup', (event) => {
                if (isAnswerField(event.target)) {
                    return;
                }

                window.getSelection()?.removeAllRanges();
            });
        });
    }

    if (screenshotProtection) {
        const screenshotKeys = new Set(['printscreen', 'printscrn', 'prtsc']);

        const screenshotShortcutType = (event) => {
            const key = String(event.key || '').toLowerCase();
            const code = String(event.code || '').toLowerCase();
            const printShortcut = (event.ctrlKey || event.metaKey) && key === 'p';
            const snippingShortcut = event.shiftKey && (event.ctrlKey || event.metaKey) && key === 's';
            const printScreenShortcut = screenshotKeys.has(key) || screenshotKeys.has(code);

            if (printShortcut) {
                return 'print_shortcut';
            }

            if (printScreenShortcut || snippingShortcut) {
                return 'screenshot_shortcut';
            }

            return null;
        };

        const handleScreenshotShortcut = (event) => {
            const eventType = screenshotShortcutType(event);

            if (! eventType) {
                return;
            }

            event.preventDefault();
            recordWarning(eventType);
        };

        document.addEventListener('keydown', handleScreenshotShortcut);
        document.addEventListener('keyup', handleScreenshotShortcut);
        window.addEventListener('beforeprint', () => recordWarning('print_shortcut'));
    }

    if (detectTabSwitch) {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                recordWarning('tab_hidden');
            }
        });

        window.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (! document.hidden && ! isTextAnswerField()) {
                    recordWarning('window_blur');
                }
            }, 150);
        });

        const getViewportSize = () => ({
            width: Math.round(window.visualViewport?.width || window.innerWidth || document.documentElement.clientWidth || 0),
            height: Math.round(window.visualViewport?.height || window.innerHeight || document.documentElement.clientHeight || 0),
        });

        let viewportBaseline = getViewportSize();
        let lastFloatingWindowWarningAt = 0;
        let lastOrientationChangeAt = 0;

        const refreshViewportBaseline = (size = getViewportSize()) => {
            viewportBaseline = {
                width: Math.max(viewportBaseline.width, size.width),
                height: Math.max(viewportBaseline.height, size.height),
            };
        };

        const detectFloatingWindowMode = () => {
            const now = Date.now();

            if (
                isAutoSubmitting
                || isFinalSubmitting
                || isSubmitConfirmOpen
                || now - lastOrientationChangeAt < 1800
                || now - lastFloatingWindowWarningAt < 8000
            ) {
                return;
            }

            const currentViewport = getViewportSize();

            if (currentViewport.width >= viewportBaseline.width || currentViewport.height >= viewportBaseline.height) {
                refreshViewportBaseline(currentViewport);
                return;
            }

            if (isTextAnswerField()) {
                return;
            }

            const widthRatio = viewportBaseline.width > 0 ? currentViewport.width / viewportBaseline.width : 1;
            const heightRatio = viewportBaseline.height > 0 ? currentViewport.height / viewportBaseline.height : 1;
            const baselineArea = viewportBaseline.width * viewportBaseline.height;
            const currentArea = currentViewport.width * currentViewport.height;
            const areaRatio = baselineArea > 0 ? currentArea / baselineArea : 1;
            const suspiciousWidth = viewportBaseline.width >= 330 && widthRatio < 0.82;
            const suspiciousHeight = viewportBaseline.height >= 520 && heightRatio < 0.72;
            const suspiciousArea = baselineArea >= 180000 && areaRatio < 0.64;

            if (suspiciousWidth || suspiciousHeight || suspiciousArea) {
                lastFloatingWindowWarningAt = now;
                recordWarning('floating_window');
            }
        };

        const queueFloatingWindowCheck = () => {
            window.setTimeout(detectFloatingWindowMode, 350);
        };

        window.addEventListener('orientationchange', () => {
            lastOrientationChangeAt = Date.now();
            window.setTimeout(() => {
                viewportBaseline = getViewportSize();
            }, 1200);
        });
        window.addEventListener('resize', queueFloatingWindowCheck);
        window.visualViewport?.addEventListener('resize', queueFloatingWindowCheck);
        window.setTimeout(() => {
            viewportBaseline = getViewportSize();
        }, 700);
    }

    if (oneQuestionMode) {
        setCurrentQuestion(0);
    }

    const assessmentForm = document.getElementById('assessmentAttemptForm');
    const submitConfirmModalElement = document.getElementById('submitConfirmModal');
    const submitConfirmModal = submitConfirmModalElement
        ? new bootstrap.Modal(submitConfirmModalElement)
        : null;

    submitConfirmModalElement?.addEventListener('shown.bs.modal', () => {
        isSubmitConfirmOpen = true;
    });

    submitConfirmModalElement?.addEventListener('hidden.bs.modal', () => {
        isSubmitConfirmOpen = false;
    });

    document.getElementById('confirmSubmitAssessment')?.addEventListener('click', () => {
        if (! assessmentForm) {
            return;
        }

        isFinalSubmitting = true;
        isSubmitConfirmOpen = true;

        const button = document.getElementById('confirmSubmitAssessment');

        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
        }

        assessmentForm.submit();
    });

    assessmentForm?.addEventListener('submit', (event) => {
        if (isAutoSubmitting || isFinalSubmitting) {
            return;
        }

        event.preventDefault();
        updateProgress();

        const answered = answeredQuestions().length;
        const message = answered < cards.length
            ? `You answered ${answered} of ${cards.length} questions. You may still submit, but unanswered questions will be marked as blank.`
            : 'All questions have an answer. Submit your assessment now?';

        document.getElementById('submitConfirmMessage').textContent = message;
        document.getElementById('submitConfirmAnswered').textContent = `${answered} / ${cards.length}`;

        if (submitConfirmModal) {
            isSubmitConfirmOpen = true;
            submitConfirmModal.show();
        } else if (window.confirm(message)) {
            isFinalSubmitting = true;
            assessmentForm.submit();
        }
    });

    updateProgress();
    updateCountdown();
    window.setInterval(updateCountdown, 1000);
</script>
