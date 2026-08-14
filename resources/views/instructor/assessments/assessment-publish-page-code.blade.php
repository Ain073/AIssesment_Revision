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
        const publishConfirmModalElement = document.getElementById('publishConfirmModal');
        const publishConfirmModal = publishConfirmModalElement
            ? new bootstrap.Modal(publishConfirmModalElement)
            : null;
        const confirmPublishButton = document.getElementById('confirmPublishAssessment');
        let publishConfirmed = false;

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
                const checkbox = option.querySelector('.class-checkbox');

                option.classList.toggle('d-none', ! matchesSubject);

                if (checkbox) {
                    checkbox.disabled = ! matchesSubject;

                    if (! matchesSubject) {
                        checkbox.checked = false;
                    }

                    if (matchesSubject && checkbox.checked) {
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

        publishForm?.addEventListener('submit', (event) => {
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
    })();
</script>
