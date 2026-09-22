<script>
    (() => {
        const subjectSelect = document.getElementById('subject_id');
        const assessmentSelect = document.getElementById('assessment_key');
        const classOptions = document.querySelectorAll('.class-option');
        const classCheckboxes = document.querySelectorAll('.class-checkbox');
        const classEmptyState = document.getElementById('classEmptyState');
        const selectedClassCount = document.getElementById('selectedClassCount');
        const classDropdownLabel = document.getElementById('classDropdownLabel');
        const publishForm = document.getElementById('publishAssessmentForm');
        const availableAtInput = document.getElementById('available_at');
        const dueAtInput = document.getElementById('due_at');
        const displayModeSelect = document.getElementById('display_mode');
        const questionTimeLimitField = document.getElementById('questionTimeLimitField');
        const questionTimeLimitInput = document.getElementById('question_time_limit_seconds');
        const publishConfirmModalElement = document.getElementById('publishConfirmModal');
        const publishConfirmModal = publishConfirmModalElement
            ? new bootstrap.Modal(publishConfirmModalElement)
            : null;
        const confirmPublishButton = document.getElementById('confirmPublishAssessment');
        let publishConfirmed = false;

        const syncDueDateMinimum = () => {
            if (dueAtInput && availableAtInput?.value) {
                dueAtInput.min = availableAtInput.value;
            }
        };

        const syncQuestionTimeLimit = () => {
            const isOneQuestionMode = displayModeSelect?.value === 'one_question';

            questionTimeLimitField?.classList.toggle('d-none', ! isOneQuestionMode);

            if (questionTimeLimitInput) {
                questionTimeLimitInput.disabled = ! isOneQuestionMode;
                questionTimeLimitInput.required = isOneQuestionMode;

                if (isOneQuestionMode && ! questionTimeLimitInput.value) {
                    questionTimeLimitInput.value = '10';
                }
            }
        };

        const filterSelect = (select, subjectId) => {
            Array.from(select.options).forEach((option) => {
                if (! option.value) {
                    return;
                }

                const matchesSubject = option.dataset.subjectId === subjectId;
                option.hidden = ! matchesSubject;

                if (! matchesSubject) {
                    option.selected = false;
                }
            });
        };

        const syncClassOptions = (subjectId) => {
            let visibleCount = 0;
            let selectedCount = 0;
            const selectedNames = [];

            classOptions.forEach((option) => {
                const matchesSubject = option.dataset.subjectId === subjectId;
                const hasStudents = option.dataset.hasStudents === '1';
                const checkbox = option.querySelector('.class-checkbox');

                option.classList.toggle('d-none', ! matchesSubject);

                if (checkbox) {
                    checkbox.disabled = ! matchesSubject || ! hasStudents;

                    if (! matchesSubject || ! hasStudents) {
                        checkbox.checked = false;
                    }

                    if (matchesSubject && hasStudents && checkbox.checked) {
                        selectedCount++;
                        selectedNames.push(checkbox.dataset.className);
                    }
                }

                if (matchesSubject) {
                    visibleCount++;
                }
            });

            classEmptyState?.classList.toggle('d-none', visibleCount > 0);

            if (selectedClassCount) {
                selectedClassCount.textContent = `${selectedCount} selected`;
            }

            if (classDropdownLabel) {
                if (selectedCount === 0) {
                    classDropdownLabel.textContent = 'Select classes';
                } else if (selectedCount === 1) {
                    classDropdownLabel.textContent = selectedNames[0] ?? '1 class selected';
                } else {
                    classDropdownLabel.textContent = `${selectedCount} classes selected`;
                }
            }
        };

        const syncSubjectChoices = () => {
            const subjectId = subjectSelect.value;
            filterSelect(assessmentSelect, subjectId);
            syncClassOptions(subjectId);
        };

        subjectSelect.addEventListener('change', syncSubjectChoices);
        classCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => syncClassOptions(subjectSelect.value));
        });
        availableAtInput?.addEventListener('change', syncDueDateMinimum);
        displayModeSelect?.addEventListener('change', syncQuestionTimeLimit);

        publishForm?.addEventListener('submit', (event) => {
            syncDueDateMinimum();

            if (publishConfirmed) {
                return;
            }

            const selectedCount = Array.from(classCheckboxes).filter((checkbox) => checkbox.checked && ! checkbox.disabled).length;

            if (selectedCount === 0) {
                return;
            }

            event.preventDefault();
            publishConfirmModal?.show();
        });

        confirmPublishButton?.addEventListener('click', () => {
            if (! publishForm) {
                return;
            }

            publishConfirmed = true;
            confirmPublishButton.disabled = true;
            confirmPublishButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Publishing...';
            publishForm.requestSubmit();
        });

        syncSubjectChoices();
        syncDueDateMinimum();
        syncQuestionTimeLimit();
    })();
</script>
