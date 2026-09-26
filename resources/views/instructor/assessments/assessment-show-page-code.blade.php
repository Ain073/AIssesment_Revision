<script>
    (() => {
        const typeLabels = {{ Illuminate\Support\Js::from($itemTypes) }};
        const oldItems = Object.values({{ Illuminate\Support\Js::from($oldItems) }} ?? {});
        const validationErrors = {{ Illuminate\Support\Js::from($errors->getMessages()) }};
        const stepButtons = document.querySelectorAll('[data-step-tab]');
        const stepTriggers = document.querySelectorAll('[data-step-target]');
        const stepPanels = document.querySelectorAll('[data-step-panel]');
        const form = document.getElementById('questionBuilderForm');
        const typeSelect = document.getElementById('item_type');
        const pointsInput = document.getElementById('points');
        const addButton = document.getElementById('addQuestionButton');
        const questionBlocks = document.getElementById('questionBlocks');
        const emptyState = document.getElementById('emptyBuilderState');
        const saveButton = document.getElementById('saveQuestionsButton');
        const countBadge = document.getElementById('questionCountBadge');
        const errorSummary = document.getElementById('questionBuilderErrors');
        let questionIndex = 0;

        const showPanel = (target) => {
            stepPanels.forEach((panel) => {
                panel.classList.toggle('d-none', panel.dataset.stepPanel !== target);
            });

            stepButtons.forEach((button) => {
                button.setAttribute('aria-selected', button.dataset.stepTarget === target ? 'true' : 'false');
            });
        };

        stepTriggers.forEach((button) => {
            button.addEventListener('click', () => showPanel(button.dataset.stepTarget));
        });

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const inputName = (index, field) => `items[${index}][${field}]`;

        const fieldErrors = (index) => Object.entries(validationErrors)
            .filter(([field]) => field.startsWith(`items.${index}.`))
            .flatMap(([, messages]) => messages);

        const clearBlockErrors = () => {
            questionBlocks.querySelectorAll('[data-question-block]').forEach((block) => {
                block.classList.remove('question-block-invalid');
                block.querySelector('[data-question-errors]')?.remove();
            });
        };

        const showBlockErrors = (block, messages) => {
            if (messages.length === 0) {
                return;
            }

            block.classList.add('question-block-invalid');

            const errorBox = document.createElement('div');
            errorBox.className = 'question-error-list';
            errorBox.dataset.questionErrors = 'true';
            errorBox.innerHTML = `<ul>${messages.map((message) => `<li>${escapeHtml(message)}</li>`).join('')}</ul>`;

            block.querySelector('.question-block-body')?.prepend(errorBox);
        };

        const showErrorSummary = (messages) => {
            if (! errorSummary) {
                return;
            }

            if (messages.length === 0) {
                errorSummary.classList.add('d-none');
                errorSummary.innerHTML = '';
                return;
            }

            errorSummary.classList.remove('d-none');
            errorSummary.innerHTML = `
                <p class="fw-bold mb-2">Please review the highlighted question fields.</p>
                <ul class="mb-0">${messages.map((message) => `<li>${escapeHtml(message)}</li>`).join('')}</ul>
            `;
        };

        const refreshState = () => {
            const count = questionBlocks.querySelectorAll('[data-question-block]').length;
            questionBlocks.classList.toggle('d-none', count === 0);
            emptyState.classList.toggle('d-none', count > 0);
            saveButton.disabled = count === 0;
            countBadge.textContent = `${count} question${count === 1 ? '' : 's'}`;
        };

        const choiceFields = (index, oldItem = {}) => {
            const choices = oldItem.choices ?? [];
            const hasSelectedChoice = Object.prototype.hasOwnProperty.call(oldItem, 'correct_choice')
                && oldItem.correct_choice !== null
                && oldItem.correct_choice !== '';
            const selected = hasSelectedChoice ? String(oldItem.correct_choice) : null;
            let html = '<p class="compact-label">Choices and Correct Answer</p><div class="choice-grid">';

            for (let choiceIndex = 0; choiceIndex < 4; choiceIndex++) {
                html += `
                    <div class="choice-line">
                        <label class="choice-radio" title="Correct answer">
                            <input class="form-check-input mt-0" name="${inputName(index, 'correct_choice')}" type="radio" value="${choiceIndex}" ${selected === String(choiceIndex) ? 'checked' : ''}>
                        </label>
                        <input class="form-control" name="items[${index}][choices][]" placeholder="Choice ${choiceIndex + 1}" type="text" value="${escapeHtml(choices[choiceIndex] ?? '')}">
                    </div>
                `;
            }

            return `${html}</div>`;
        };

        const typeSpecificFields = (index, type, oldItem = {}) => {
            if (type === 'multiple_choice') {
                return choiceFields(index, oldItem);
            }

            if (type === 'true_false') {
                const selected = oldItem.true_false_answer ?? 'true';

                return `
                    <p class="compact-label">Correct Answer</p>
                    <div class="row g-2">
                        ${['true', 'false'].map((value) => `
                            <div class="col-sm-6">
                                <label class="border rounded p-3 d-flex align-items-center gap-2 h-100 bg-white">
                                    <input class="form-check-input" name="${inputName(index, 'true_false_answer')}" type="radio" value="${value}" ${selected === value ? 'checked' : ''}>
                                    <span class="fw-bold" style="color: var(--psu-navy);">${value === 'true' ? 'True' : 'False'}</span>
                                </label>
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            if (type === 'identification') {
                return `
                    <label class="form-label fw-bold text-uppercase small" for="accepted_answer_${index}">Accepted Answer</label>
                    <input class="form-control" id="accepted_answer_${index}" name="${inputName(index, 'accepted_answer')}" type="text" value="${escapeHtml(oldItem.accepted_answer ?? '')}">
                `;
            }

            if (type === 'enumeration') {
                const enumAnswers = oldItem.enum_answers ?? [''];
                const orderSensitive = oldItem.order_sensitive == '1' || oldItem.order_sensitive === true || oldItem.order_sensitive === 'true';
                let rows = '';
                const count = Math.max(enumAnswers.length, 1);

                for (let ei = 0; ei < count; ei++) {
                    rows += `
                        <div class="enum-answer-row d-flex gap-2 mb-2" data-enum-row>
                            <span class="input-group-text bg-light border rounded px-2" style="min-width:2.2rem;justify-content:center;">${ei + 1}</span>
                            <input class="form-control" name="items[${index}][enum_answers][]" type="text" placeholder="Answer ${ei + 1}" value="${escapeHtml(enumAnswers[ei] ?? '')}">
                            <button class="btn btn-sm btn-outline-danger" type="button" data-remove-enum-row title="Remove">&times;</button>
                        </div>
                    `;
                }

                return `
                    <div class="mb-2 d-flex align-items-center justify-content-between">
                        <p class="compact-label mb-0">Accepted Answers (one per slot)</p>
                        <div class="form-check form-switch ms-auto me-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="order_sensitive_${index}" name="items[${index}][order_sensitive]" value="1" ${orderSensitive ? 'checked' : ''}>
                            <label class="form-check-label small fw-semibold" for="order_sensitive_${index}">In order</label>
                        </div>
                    </div>
                    <div data-enum-rows-${index}>${rows}</div>
                    <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-add-enum-row="${index}">
                        + Add Answer Slot
                    </button>
                `;
            }

            return '<div class="alert alert-primary border-0 mb-0">Essay items are saved for manual checking.</div>';
        };

        const addQuestion = (oldItem = null) => {
            const type = oldItem?.item_type ?? typeSelect.value;
            const points = oldItem?.points ?? pointsInput.value;
            const index = questionIndex;
            questionIndex++;

            const block = document.createElement('section');
            block.className = 'question-block';
            block.dataset.questionBlock = 'true';
            block.innerHTML = `
                <div class="question-block-header">
                    <div>
                        <span class="badge text-bg-primary rounded-1 me-2">New Question</span>
                        <span class="fw-bold" style="color: var(--psu-navy);">${escapeHtml(typeLabels[type] ?? type)}</span>
                    </div>
                    <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" type="button" data-remove-question>
                        <span class="material-symbols-outlined fs-6">delete</span>
                        Remove
                    </button>
                </div>
                <div class="question-block-body">
                    <input name="${inputName(index, 'item_type')}" type="hidden" value="${escapeHtml(type)}">
                    <div class="row g-3 mb-3">
                        <div class="col-lg-9">
                            <label class="form-label fw-bold text-uppercase small" for="question_${index}">Question</label>
                            <textarea class="form-control" id="question_${index}" name="${inputName(index, 'question_text')}" required rows="3">${escapeHtml(oldItem?.question_text ?? '')}</textarea>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label fw-bold text-uppercase small" for="points_${index}">Points</label>
                            <input class="form-control" id="points_${index}" min="0.01" max="999.99" name="${inputName(index, 'points')}" required step="0.01" type="number" value="${escapeHtml(points)}">
                        </div>
                    </div>
                    ${typeSpecificFields(index, type, oldItem ?? {})}
                </div>
            `;

            questionBlocks.appendChild(block);
            showBlockErrors(block, fieldErrors(index));
            refreshState();
        };

        addButton.addEventListener('click', () => addQuestion());

        questionBlocks.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-question]');

            if (! removeButton) {
                return;
            }

            removeButton.closest('[data-question-block]')?.remove();
            refreshState();
        });

        // Enumeration: add answer slot
        questionBlocks.addEventListener('click', (event) => {
            const addRowBtn = event.target.closest('[data-add-enum-row]');

            if (! addRowBtn) {
                return;
            }

            const blockIndex = addRowBtn.dataset.addEnumRow;
            const container = questionBlocks.querySelector(`[data-enum-rows-${blockIndex}]`);

            if (! container) {
                return;
            }

            const rowCount = container.querySelectorAll('[data-enum-row]').length + 1;
            const row = document.createElement('div');
            row.className = 'enum-answer-row d-flex gap-2 mb-2';
            row.dataset.enumRow = 'true';
            row.innerHTML = `
                <span class="input-group-text bg-light border rounded px-2" style="min-width:2.2rem;justify-content:center;">${rowCount}</span>
                <input class="form-control" name="items[${blockIndex}][enum_answers][]" type="text" placeholder="Answer ${rowCount}">
                <button class="btn btn-sm btn-outline-danger" type="button" data-remove-enum-row title="Remove">&times;</button>
            `;
            container.appendChild(row);
        });

        // Enumeration: remove answer slot
        questionBlocks.addEventListener('click', (event) => {
            const removeRowBtn = event.target.closest('[data-remove-enum-row]');

            if (! removeRowBtn) {
                return;
            }

            const row = removeRowBtn.closest('[data-enum-row]');
            const container = row?.parentElement;
            row?.remove();

            // Renumber remaining rows
            if (container) {
                container.querySelectorAll('[data-enum-row]').forEach((r, i) => {
                    const label = r.querySelector('span');
                    const input = r.querySelector('input');

                    if (label) {
                        label.textContent = String(i + 1);
                    }

                    if (input) {
                        input.placeholder = `Answer ${i + 1}`;
                    }
                });
            }
        });

        questionBlocks.addEventListener('input', (event) => {
            const block = event.target.closest('[data-question-block]');

            if (block) {
                block.classList.remove('question-block-invalid');
                block.querySelector('[data-question-errors]')?.remove();
            }
        });

        questionBlocks.addEventListener('change', (event) => {
            const block = event.target.closest('[data-question-block]');

            if (block) {
                block.classList.remove('question-block-invalid');
                block.querySelector('[data-question-errors]')?.remove();
            }
        });

        form.addEventListener('submit', (event) => {
            clearBlockErrors();

            const messages = [];
            let firstInvalidBlock = null;

            questionBlocks.querySelectorAll('[data-question-block]').forEach((block, order) => {
                const questionNumber = order + 1;
                const blockMessages = [];
                const type = block.querySelector('input[name$="[item_type]"]')?.value;
                const questionText = block.querySelector('textarea[name$="[question_text]"]')?.value.trim();
                const points = Number(block.querySelector('input[name$="[points]"]')?.value);

                if (! questionText) {
                    blockMessages.push(`Question ${questionNumber}: Enter the question text.`);
                }

                if (! Number.isFinite(points) || points <= 0) {
                    blockMessages.push(`Question ${questionNumber}: Enter a valid point value.`);
                }

                if (type === 'multiple_choice') {
                    const choices = Array.from(block.querySelectorAll('input[name$="[choices][]"]'));
                    const filledChoices = choices.filter((choice) => choice.value.trim() !== '');
                    const selectedChoice = block.querySelector('input[name$="[correct_choice]"]:checked');

                    if (filledChoices.length < 2) {
                        blockMessages.push(`Question ${questionNumber}: Enter at least two choices.`);
                    }

                    if (! selectedChoice) {
                        blockMessages.push(`Question ${questionNumber}: Select the correct choice.`);
                    } else {
                        const selectedChoiceInput = choices[Number(selectedChoice.value)];

                        if (! selectedChoiceInput || selectedChoiceInput.value.trim() === '') {
                            blockMessages.push(`Question ${questionNumber}: The selected correct choice must have text.`);
                        }
                    }
                }

                if (type === 'true_false' && ! block.querySelector('input[name$="[true_false_answer]"]:checked')) {
                    blockMessages.push(`Question ${questionNumber}: Select True or False as the correct answer.`);
                }

                if (type === 'identification' && ! block.querySelector('input[name$="[accepted_answer]"]')?.value.trim()) {
                    blockMessages.push(`Question ${questionNumber}: Enter the accepted answer.`);
                }

                if (type === 'enumeration') {
                    const enumInputs = Array.from(block.querySelectorAll('input[name$="[enum_answers][]"]'));
                    const filledEnumAnswers = enumInputs.filter((inp) => inp.value.trim() !== '');

                    if (filledEnumAnswers.length < 1) {
                        blockMessages.push(`Question ${questionNumber}: Enter at least one accepted answer for enumeration.`);
                    }
                }

                if (blockMessages.length > 0) {
                    firstInvalidBlock ??= block;
                    messages.push(...blockMessages);
                    showBlockErrors(block, blockMessages);
                }
            });

            if (messages.length === 0) {
                showErrorSummary([]);
                return;
            }

            event.preventDefault();
            showErrorSummary(messages);
            firstInvalidBlock?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });

        if (oldItems.length > 0) {
            oldItems.forEach((item) => addQuestion(item));
        }

        refreshState();
    })();
</script>

@if ($errors->any() && ! $detailsHasErrors)
    <script>
        const questionBuilderModal = document.getElementById('questionBuilderModal');

        if (questionBuilderModal) {
            bootstrap.Modal.getOrCreateInstance(questionBuilderModal).show();
        }
    </script>
@endif
