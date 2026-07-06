<script>
    (() => {
        const typeLabels = {{ Illuminate\Support\Js::from($itemTypes) }};
        const oldItems = Object.values({{ Illuminate\Support\Js::from($oldItems) }} ?? {});
        const stepButtons = document.querySelectorAll('[data-step-tab]');
        const stepTriggers = document.querySelectorAll('[data-step-target]');
        const stepPanels = document.querySelectorAll('[data-step-panel]');
        const typeSelect = document.getElementById('item_type');
        const addButton = document.getElementById('addQuestionButton');
        const questionBlocks = document.getElementById('questionBlocks');
        const emptyState = document.getElementById('emptyBuilderState');
        const saveButton = document.getElementById('saveQuestionsButton');
        const countBadge = document.getElementById('questionCountBadge');
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

        const refreshState = () => {
            const count = questionBlocks.querySelectorAll('[data-question-block]').length;
            questionBlocks.classList.toggle('d-none', count === 0);
            emptyState.classList.toggle('d-none', count > 0);
            saveButton.disabled = count === 0;
            countBadge.textContent = `${count} question${count === 1 ? '' : 's'}`;
        };

        const choiceFields = (index, oldItem = {}) => {
            const choices = oldItem.choices ?? [];
            const selected = String(oldItem.correct_choice ?? 0);
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

            return '<div class="alert alert-primary border-0 mb-0">Essay items are saved for manual checking.</div>';
        };

        const addQuestion = (oldItem = null) => {
            const type = typeSelect.value;
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
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="question_${index}">Question</label>
                        <textarea class="form-control" id="question_${index}" name="${inputName(index, 'question_text')}" required rows="3">${escapeHtml(oldItem?.question_text ?? '')}</textarea>
                    </div>
                    ${typeSpecificFields(index, type, oldItem ?? {})}
                </div>
            `;

            questionBlocks.appendChild(block);
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

        if (oldItems.length > 0) {
            oldItems.forEach((item) => addQuestion(item));
        }

        refreshState();
    })();
</script>

@if ($errors->any() && ! $errors->hasAny(['title', 'description', 'type', 'report_category', 'reporting_term', 'instructions']))
    <script>
        const questionBuilderModal = document.getElementById('questionBuilderModal');

        if (questionBuilderModal) {
            bootstrap.Modal.getOrCreateInstance(questionBuilderModal).show();
        }
    </script>
@endif
