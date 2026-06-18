@extends('layouts.portal')

@section('title', 'Publish Assessment | AIssessment Instructor')
@section('header', 'Publish Assessment')

@php
    $selectedClassIds = collect(old('class_ids', []))->map(fn ($id) => (string) $id)->all();
@endphp

@push('styles')
    <style>
        .publish-panel {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .publish-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .setting-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .toggle-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .toggle-box {
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            padding: 0.85rem 1rem;
            background: #f8faff;
        }

        .security-box {
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            padding: 1rem;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        }

        .security-box:has(.form-check-input:checked) {
            border-color: var(--psu-navy-2);
            background: #edf3ff;
            box-shadow: 0 10px 24px rgba(9, 39, 216, 0.08);
        }

        .class-dropdown-menu {
            width: 100%;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #f8faff;
            padding: 1rem;
            max-height: 260px;
            overflow-y: auto;
        }

        .class-dropdown-button {
            min-height: calc(3.5rem + 2px);
            justify-content: space-between;
            text-align: left;
        }

        .class-option {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
        }

        .class-option:last-child {
            margin-bottom: 0;
        }

        .class-empty {
            border: 1px dashed #b9c5e7;
            border-radius: 0.5rem;
            background: #fff;
            padding: 1rem;
            color: var(--psu-muted);
        }

        @media (max-width: 991.98px) {
            .setting-grid,
            .toggle-row {
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
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">Publish Assessment</h1>
            <p class="text-secondary mb-0">Choose a subject, reusable assessment, and class.</p>
        </div>
        <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments', ['tab' => 'published']) }}">
            <span class="material-symbols-outlined fs-5">task_alt</span>
            Published Assessments
        </a>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <section class="publish-panel overflow-hidden">
                <div class="publish-header px-4 py-3">
                    <h2 class="h4 mb-0">Publishing Setup</h2>
                </div>

                <form action="{{ route('instructor.assessments.publish.selected') }}" method="POST" class="p-4" id="publishAssessmentForm">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="subject_id">Subject</label>
                            <select class="form-select form-select-lg" id="subject_id" name="subject_id" required>
                                <option value="">Select subject</option>
                                @foreach ($handledSubjects as $subject)
                                    <option value="{{ $subject->subject_id }}" @selected((string) $selectedSubjectId === (string) $subject->subject_id)>
                                        {{ $subject->subject_code }} - {{ $subject->subject_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="assessment_id">Assessment</label>
                            <select class="form-select form-select-lg" id="assessment_id" name="assessment_id" required>
                                <option value="">Select assessment</option>
                                @foreach ($assessments as $assessment)
                                    <option
                                        value="{{ $assessment->assessment_id }}"
                                        data-subject-id="{{ $assessment->subject_id }}"
                                        data-items-count="{{ $assessment->items_count }}"
                                        @selected((string) $selectedAssessmentId === (string) $assessment->assessment_id)
                                        @disabled($assessment->items_count === 0)
                                    >
                                        {{ $assessment->title }}{{ $assessment->items_count === 0 ? ' (needs items)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <label class="form-label fw-bold text-uppercase small mb-0">Classes Under Selected Subject</label>
                                <span class="small text-secondary" id="selectedClassCount">0 selected</span>
                            </div>

                            <div class="dropdown w-100">
                                <button
                                    class="btn btn-light border class-dropdown-button w-100 d-flex align-items-center gap-3"
                                    id="classDropdownButton"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    type="button"
                                    aria-expanded="false"
                                >
                                    <span id="classDropdownLabel">Select classes</span>
                                    <span class="material-symbols-outlined fs-5">expand_more</span>
                                </button>

                                <div class="dropdown-menu class-dropdown-menu shadow-sm" aria-labelledby="classDropdownButton">
                                    @foreach ($classes as $class)
                                        <label
                                            class="class-option"
                                            data-subject-id="{{ $class->subject_id }}"
                                        >
                                            <input
                                                class="form-check-input mt-1 class-checkbox"
                                                name="class_ids[]"
                                                type="checkbox"
                                                value="{{ $class->class_id }}"
                                                data-class-name="{{ $class->class_name }}"
                                                @checked(in_array((string) $class->class_id, $selectedClassIds, true))
                                            >
                                            <span>
                                                <span class="fw-bold d-block" style="color: var(--psu-navy);">{{ $class->class_name }}</span>
                                                <span class="small text-secondary">{{ $class->school_year }}{{ $class->subject ? ' - '.$class->subject->subject_code : '' }}</span>
                                            </span>
                                        </label>
                                    @endforeach

                                    <div class="class-empty d-none" id="classEmptyState">
                                        Select a subject with handled classes before publishing.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="setting-grid mt-4">
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="available_at">Available At</label>
                            <input class="form-control" id="available_at" name="available_at" type="datetime-local" value="{{ old('available_at') }}">
                        </div>
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="due_at">Due At</label>
                            <input class="form-control" id="due_at" name="due_at" type="datetime-local" value="{{ old('due_at') }}">
                        </div>
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="attempt_limit">Attempt Limit</label>
                            <input class="form-control" id="attempt_limit" max="10" min="1" name="attempt_limit" required type="number" value="{{ old('attempt_limit', 1) }}">
                        </div>
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="warning_limit">Warning Limit</label>
                            <input class="form-control" id="warning_limit" max="20" min="0" name="warning_limit" type="number" value="{{ old('warning_limit') }}">
                        </div>
                    </div>

                    <div class="toggle-box mt-4">
                        <label class="form-label fw-bold text-uppercase small" for="display_mode">Question Display</label>
                        <select class="form-select form-select-lg" id="display_mode" name="display_mode" required>
                            <option value="all_questions" @selected(old('display_mode', 'all_questions') === 'all_questions')>Show all questions</option>
                            <option value="one_question" @selected(old('display_mode') === 'one_question')>One question at a time</option>
                        </select>
                        <div class="form-text">Show all means no question pagination. One at a time uses Next Question.</div>
                    </div>

                    <div class="toggle-row mt-4">
                        <label class="toggle-box d-flex align-items-center gap-2 mb-0">
                            <input class="form-check-input mt-0" name="score_visibility" type="checkbox" value="1" @checked(old('score_visibility'))>
                            <span class="fw-semibold" style="color: var(--psu-navy);">Show Scores</span>
                        </label>
                        <label class="toggle-box d-flex align-items-center gap-2 mb-0">
                            <input class="form-check-input mt-0" name="answer_visibility" type="checkbox" value="1" @checked(old('answer_visibility'))>
                            <span class="fw-semibold" style="color: var(--psu-navy);">Show Answers</span>
                        </label>
                        <label class="toggle-box d-flex align-items-center gap-2 mb-0">
                            <input class="form-check-input mt-0" name="shuffle_items" type="checkbox" value="1" @checked(old('shuffle_items'))>
                            <span class="fw-semibold" style="color: var(--psu-navy);">Shuffle Items</span>
                        </label>
                        <label class="toggle-box d-flex align-items-center gap-2 mb-0">
                            <input class="form-check-input mt-0" name="shuffle_choices" type="checkbox" value="1" @checked(old('shuffle_choices'))>
                            <span class="fw-semibold" style="color: var(--psu-navy);">Shuffle Choices</span>
                        </label>
                    </div>

                    <div class="toggle-box d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                        <div>
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">Security Settings</p>
                            <p class="small text-secondary mb-0">Open the floating panel to choose student restrictions.</p>
                        </div>
                        <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" data-bs-target="#securitySettingsModal" data-bs-toggle="modal" type="button">
                            <span class="material-symbols-outlined fs-5">shield_lock</span>
                            Show Security Settings
                        </button>
                    </div>

                    <div class="modal fade" id="securitySettingsModal" tabindex="-1" aria-labelledby="securitySettingsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header publish-header">
                                    <div>
                                        <h3 class="modal-title h4" id="securitySettingsModalLabel">Security Settings</h3>
                                        <p class="small text-white-50 mb-0">Choose what will be enabled when students start.</p>
                                    </div>
                                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="d-grid gap-3">
                                        <label class="security-box mb-0">
                                            <input class="form-check-input mt-1" name="prevent_copy_paste" type="checkbox" value="1" @checked(old('prevent_copy_paste'))>
                                            <span>
                                                <span class="fw-bold d-block" style="color: var(--psu-navy);">No Copy / Paste</span>
                                                <span class="small text-secondary">Blocks copy, paste, cut, and right click.</span>
                                            </span>
                                        </label>

                                        <label class="security-box mb-0">
                                            <input class="form-check-input mt-1" name="detect_tab_switch" type="checkbox" value="1" @checked(old('detect_tab_switch'))>
                                            <span>
                                                <span class="fw-bold d-block" style="color: var(--psu-navy);">Detect Tab Switch</span>
                                                <span class="small text-secondary">Adds warning when student leaves the assessment tab.</span>
                                            </span>
                                        </label>

                                        <label class="security-box mb-0">
                                            <input class="form-check-input mt-1" name="screenshot_protection" type="checkbox" value="1" @checked(old('screenshot_protection'))>
                                            <span>
                                                <span class="fw-bold d-block" style="color: var(--psu-navy);">Screenshot Protection</span>
                                                <span class="small text-secondary">Shows watermark and blocks print/screenshot shortcuts when possible.</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-psu px-4" data-bs-dismiss="modal" type="button">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a class="btn btn-outline-secondary px-4" href="{{ route('instructor.assessments') }}">Cancel</a>
                        <button class="btn btn-psu px-4 d-inline-flex align-items-center gap-2" type="submit">
                            <span class="material-symbols-outlined fs-5">publish</span>
                            Publish
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const subjectSelect = document.getElementById('subject_id');
            const assessmentSelect = document.getElementById('assessment_id');
            const classOptions = document.querySelectorAll('.class-option');
            const classCheckboxes = document.querySelectorAll('.class-checkbox');
            const classEmptyState = document.getElementById('classEmptyState');
            const selectedClassCount = document.getElementById('selectedClassCount');
            const classDropdownLabel = document.getElementById('classDropdownLabel');

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

            syncSubjectChoices();
        })();
    </script>
@endpush
