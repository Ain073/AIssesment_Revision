@extends('layouts.portal')

@section('title', $assessment->title . ' | AIssessment Instructor')
@section('header', 'Question Builder')

@php
    $oldItems = old('items', []);
@endphp

@push('styles')
    <style>
        .builder-card,
        .setup-card,
        .saved-card,
        .details-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .builder-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .step-track {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .step-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            text-align: left;
            width: 100%;
        }

        .step-pill.active,
        .step-pill[aria-selected="true"] {
            border-color: var(--psu-navy-2);
            box-shadow: 0 12px 24px rgba(9, 39, 216, 0.12);
        }

        .step-pill.clickable {
            cursor: pointer;
        }

        .step-pill.clickable:hover,
        .step-pill.clickable:focus {
            border-color: var(--psu-navy-2);
            box-shadow: 0 12px 24px rgba(9, 39, 216, 0.08);
        }

        .step-number {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #edf2ff;
            color: var(--psu-navy);
            font-weight: 800;
            flex-shrink: 0;
        }

        .step-pill.active .step-number,
        .step-pill[aria-selected="true"] .step-number {
            background: var(--psu-navy-2);
            color: #fff;
        }

        .setup-grid {
            display: grid;
            grid-template-columns: minmax(240px, 1fr) 140px minmax(180px, auto);
            gap: 0.85rem;
            align-items: end;
        }

        .compact-label {
            color: var(--psu-muted);
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 0.45rem;
        }

        .generated-list {
            display: grid;
            gap: 0.75rem;
        }

        .question-block {
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #f8faff;
            overflow: hidden;
        }

        .question-block-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1rem;
            background: #edf2ff;
            border-bottom: 1px solid var(--psu-line);
        }

        .question-block-body {
            padding: 1rem;
        }

        .choice-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
        }

        .choice-line {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            gap: 0.55rem;
            align-items: center;
        }

        .choice-radio {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
        }

        .builder-empty {
            border: 1px dashed #b9c5e7;
            border-radius: 0.5rem;
            background: #f8faff;
            padding: 2rem;
            text-align: center;
        }

        .saved-question-list {
            display: grid;
            gap: 1rem;
        }

        .saved-question {
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #f8faff;
            overflow: hidden;
        }

        .saved-question-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.9rem 1rem;
            background: #edf2ff;
            border-bottom: 1px solid var(--psu-line);
        }

        .saved-choice-list {
            display: grid;
            gap: 0.45rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .saved-choice {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
            padding: 0.6rem 0.75rem;
        }

        @media (max-width: 1199.98px) {
            .setup-grid,
            .choice-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .step-track {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.assessments') }}" style="color: var(--psu-navy-2);">
                <span class="material-symbols-outlined align-middle fs-6">arrow_back</span>
                Back to Assessments
            </a>
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">{{ $assessment->title }}</h1>
            <p class="text-secondary mb-0">{{ $assessment->subject?->subject_code }} - {{ $assessment->subject?->subject_name }}</p>
        </div>
        <span class="badge text-bg-primary rounded-1 px-3 py-2">{{ $assessment->items_count }} question{{ $assessment->items_count === 1 ? '' : 's' }}</span>
    </div>

    <section class="step-track mb-4">
        <button class="step-pill clickable" data-step-tab data-step-target="detailsPanel" type="button" aria-selected="{{ $errors->hasAny(['title', 'description', 'type', 'report_category', 'reporting_term', 'instructions']) ? 'true' : 'false' }}">
            <span class="step-number">1</span>
            <div>
                <p class="fw-bold mb-0" style="color: var(--psu-navy);">Details and Instructions</p>
                <p class="small text-secondary mb-0">Edit assessment setup</p>
            </div>
        </button>
        <button class="step-pill clickable" data-bs-target="#questionBuilderModal" data-bs-toggle="modal" type="button" aria-selected="false">
            <span class="step-number">2</span>
            <div>
                <p class="fw-bold mb-0" style="color: var(--psu-navy);">Question Builder</p>
                <p class="small text-secondary mb-0">Open floating question form</p>
            </div>
        </button>
    </section>

    <section class="details-card mb-4 {{ $errors->hasAny(['title', 'description', 'type', 'report_category', 'reporting_term', 'instructions']) ? '' : 'd-none' }}" id="detailsPanel" data-step-panel="detailsPanel">
            <form action="{{ route('instructor.assessments.update', $assessment) }}" method="POST" class="p-4">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold text-uppercase small" for="title">Title</label>
                        <input class="form-control" id="title" name="title" required type="text" value="{{ old('title', $assessment->title) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="type">Type</label>
                        <select class="form-select" id="type" name="type" required>
                            @foreach ($assessmentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $assessment->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="report_category">Report Category</label>
                        <select class="form-select" id="report_category" name="report_category" required>
                            @foreach ($reportCategories as $value => $label)
                                <option value="{{ $value }}" @selected(old('report_category', $assessment->report_category) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="reporting_term">Reporting Term</label>
                        <select class="form-select" id="reporting_term" name="reporting_term" required>
                            @foreach ($reportingTerms as $value => $label)
                                <option value="{{ $value }}" @selected(old('reporting_term', $assessment->reporting_term) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-uppercase small" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $assessment->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-uppercase small" for="instructions">Instructions</label>
                        <textarea class="form-control" id="instructions" name="instructions" rows="4">{{ old('instructions', $assessment->instructions) }}</textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-outline-secondary px-4" data-step-target="questionsPanel" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit">Save Details</button>
                </div>
            </form>
    </section>

    <section class="saved-card overflow-hidden mb-4">
        <div class="builder-header px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h4 mb-0">Saved Questions</h2>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge text-bg-light rounded-1">{{ $assessment->items_count }} saved</span>
                <button class="btn btn-light d-inline-flex align-items-center gap-2" data-bs-target="#questionBuilderModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">add</span>
                    Add Questions
                </button>
            </div>
        </div>

        <div class="p-4">
            @if ($assessment->items->isNotEmpty())
                <div class="saved-question-list">
                    @foreach ($assessment->items as $item)
                        <article class="saved-question">
                            <div class="saved-question-header">
                                <div>
                                    <span class="badge text-bg-primary rounded-1 me-2">Question {{ $item->sort_order }}</span>
                                    <span class="fw-bold" style="color: var(--psu-navy);">{{ $itemTypes[$item->item_type] ?? ucfirst(str_replace('_', ' ', $item->item_type)) }}</span>
                                </div>
                                <span class="badge text-bg-light border rounded-1">{{ $item->points }} point{{ (float) $item->points == 1.0 ? '' : 's' }}</span>
                            </div>

                            <div class="p-3">
                                <p class="fw-semibold mb-3" style="color: var(--psu-navy);">{{ $item->question_text }}</p>

                                @if ($item->choices->isNotEmpty())
                                    <p class="compact-label">Saved Answers</p>
                                    <ul class="saved-choice-list">
                                        @foreach ($item->choices as $choice)
                                            <li class="saved-choice">
                                                <span>{{ $choice->choice_text }}</span>
                                                @if ($choice->is_correct)
                                                    <span class="badge text-bg-success rounded-1">Correct</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-secondary mb-0">No saved choices for this item.</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="builder-empty">
                    <span class="material-symbols-outlined fs-1 mb-2" style="color: var(--psu-navy-2);">quiz</span>
                    <h3 class="h5" style="color: var(--psu-navy);">No saved questions yet</h3>
                    <p class="text-secondary mb-0">Use Add Questions to open the floating form.</p>
                </div>
            @endif
        </div>
    </section>

    <div class="modal fade" id="questionBuilderModal" tabindex="-1" aria-labelledby="questionBuilderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <form action="{{ route('instructor.assessments.items.store', $assessment) }}" method="POST" id="questionBuilderForm" class="modal-content">
                @csrf

                <div class="modal-header">
                    <h3 class="modal-title h4" id="questionBuilderModalLabel">Add Questions</h3>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <section class="setup-card p-4 mb-4">
                        <div class="setup-grid">
                            <div>
                                <label class="form-label fw-bold text-uppercase small" for="item_type">Question Type</label>
                                <select class="form-select form-select-lg" id="item_type" name="item_type" required>
                                    @foreach ($itemTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('item_type', 'multiple_choice') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label fw-bold text-uppercase small" for="points">Points</label>
                                <input class="form-control form-control-lg" id="points" min="0.01" max="999.99" name="points" required step="0.01" type="number" value="{{ old('points', 1) }}">
                            </div>
                            <div>
                                <button class="btn btn-psu btn-lg w-100 d-inline-flex justify-content-center align-items-center gap-2" id="addQuestionButton" type="button">
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Add Question Field
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="builder-card overflow-hidden">
                        <div class="builder-header px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h2 class="h4 mb-0">Question Fields</h2>
                            <span class="badge text-bg-light rounded-1" id="questionCountBadge">0 questions</span>
                        </div>

                        <div class="p-4">
                            <div class="builder-empty" id="emptyBuilderState">
                                <span class="material-symbols-outlined fs-1 mb-2" style="color: var(--psu-navy-2);">post_add</span>
                                <h3 class="h5" style="color: var(--psu-navy);">No question fields yet</h3>
                                <p class="text-secondary mb-0">Click Add Question Field to start building this assessment.</p>
                            </div>

                            <div class="generated-list d-none" id="questionBlocks"></div>
                        </div>
                    </section>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4 d-inline-flex align-items-center gap-2" id="saveQuestionsButton" type="submit" disabled>
                        <span class="material-symbols-outlined fs-5">save</span>
                        Save Questions
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
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
@endpush
