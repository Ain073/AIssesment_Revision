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
                            <label class="form-label fw-bold text-uppercase small" for="points">Default Points</label>
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
