@extends('layouts.portal')

@section('title', 'Publish Assessment | AIssessment Instructor')
@section('header', 'Publish Assessment')

@php
    $selectedClassKeys = collect(old('class_keys', []))->map(fn ($key) => (string) $key)->all();
    $minimumScheduleTime = now()->format('Y-m-d\TH:i');
@endphp

@push('styles')
    <x-page-style path="css/pages/instructor/assessments/publish.css" />
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.assessments') }}" style="color: var(--psu-navy-2);">
                <span class="material-symbols-outlined align-middle fs-6">arrow_back</span>
                Back to Assessments
            </a>
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">Publish Assessment</h1>
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

                <form action="{{ route('instructor.assessments.publish.selected') }}" method="POST" class="publish-form p-3 p-lg-4" id="publishAssessmentForm">
                    @csrf

                    <div class="publish-section">
                        <div class="publish-section-title mb-3">Assessment Details</div>
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-bold text-uppercase small" for="subject_id">Subject</label>
                                <select class="form-select compact-select" id="subject_id" name="subject_id" required>
                                    <option value="">Select subject</option>
                                    @foreach ($handledSubjects as $subject)
                                        <option value="{{ $subject->subject_id }}" @selected((string) $selectedSubjectId === (string) $subject->subject_id)>
                                            {{ $subject->subject_code }} - {{ \Illuminate\Support\Str::limit($subject->subject_name, 45) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-bold text-uppercase small" for="assessment_key">Assessment</label>
                                <select class="form-select compact-select" id="assessment_key" name="assessment_key" required>
                                    <option value="">Select assessment</option>
                                    @foreach ($assessments as $assessment)
                                        <option
                                            value="{{ $assessment->public_id }}"
                                            data-subject-id="{{ $assessment->subject_id }}"
                                            data-items-count="{{ $assessment->items_count }}"
                                            @selected((string) $selectedAssessmentKey === (string) $assessment->public_id)
                                            @disabled($assessment->items_count === 0)
                                        >
                                            {{ \Illuminate\Support\Str::limit($assessment->title, 50) }}{{ $assessment->items_count === 0 ? ' (needs items)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
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
                                        @php
                                            $studentCount = (int) ($class->students_count ?? $class->enrolledStudentsCount());
                                            $hasStudents = $studentCount > 0;
                                        @endphp
                                        <label
                                            class="class-option {{ $hasStudents ? '' : 'is-disabled' }}"
                                            data-subject-id="{{ $class->subject_id }}"
                                            data-has-students="{{ $hasStudents ? '1' : '0' }}"
                                        >
                                            <input
                                                class="form-check-input mt-1 class-checkbox"
                                                name="class_keys[]"
                                                type="checkbox"
                                                value="{{ $class->public_id }}"
                                                data-class-name="{{ $class->class_name }}"
                                                @checked(in_array((string) $class->public_id, $selectedClassKeys, true))
                                                @disabled(! $hasStudents)
                                            >
                                            <span>
                                                <span class="fw-bold d-block" style="color: var(--psu-navy);">{{ $class->class_name }}</span>
                                                <span class="small text-secondary">
                                                    {{ $class->school_year }}{{ $class->subject ? ' - '.$class->subject->subject_code : '' }}
                                                    @if (! $hasStudents)
                                                        <span class="d-block text-danger fw-semibold">No students yet</span>
                                                    @endif
                                                </span>
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

                    <div class="publish-section mt-3">
                        <div class="publish-section-title mb-3">Schedule And Limits</div>
                        <div class="setting-grid">
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="available_at">Available At</label>
                            <input class="form-control compact-input @error('available_at') is-invalid @enderror" id="available_at" min="{{ $minimumScheduleTime }}" name="available_at" type="datetime-local" value="{{ old('available_at', $minimumScheduleTime) }}">
                            @error('available_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="due_at">Due At</label>
                            <input class="form-control compact-input @error('due_at') is-invalid @enderror" id="due_at" min="{{ $minimumScheduleTime }}" name="due_at" type="datetime-local" value="{{ old('due_at') }}">
                            @error('due_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="attempt_limit">Attempt Limit</label>
                            <input class="form-control compact-input" id="attempt_limit" max="10" min="1" name="attempt_limit" required type="number" value="{{ old('attempt_limit', 1) }}">
                        </div>
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="warning_limit">Warning Limit</label>
                            <input class="form-control compact-input" id="warning_limit" max="20" min="0" name="warning_limit" type="number" value="{{ old('warning_limit', 3) }}">
                        </div>
                        </div>
                    </div>

                    <div class="publish-section mt-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-4">
                                <label class="form-label fw-bold text-uppercase small" for="display_mode">Question Display</label>
                                <select class="form-select compact-select" id="display_mode" name="display_mode" required>
                                    <option value="all_questions" @selected(old('display_mode', 'all_questions') === 'all_questions')>Show all questions</option>
                                    <option value="one_question" @selected(old('display_mode') === 'one_question')>One question at a time</option>
                                </select>
                            </div>

                            <div class="col-lg-3" id="questionTimeLimitField">
                                <label class="form-label fw-bold text-uppercase small" for="question_time_limit_seconds">Seconds Per Question</label>
                                <input class="form-control compact-input @error('question_time_limit_seconds') is-invalid @enderror" id="question_time_limit_seconds" min="5" max="3600" name="question_time_limit_seconds" type="number" value="{{ old('question_time_limit_seconds', 10) }}">
                                @error('question_time_limit_seconds')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-5">
                                <div class="toggle-row">
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
                            </div>
                        </div>
                    </div>

                    <div class="publish-section mt-3">
                        <div class="publish-section-title mb-3">Security Settings</div>
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <label class="security-box h-100 mb-0">
                                    <input class="form-check-input mt-1" name="prevent_copy_paste" type="checkbox" value="1" @checked(old('prevent_copy_paste'))>
                                    <span>
                                        <span class="fw-bold d-block" style="color: var(--psu-navy);">No Copy / Paste</span>
                                        <span class="small text-secondary">Blocks copy, paste, cut, and right click.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-lg-4">
                                <label class="security-box h-100 mb-0">
                                    <input class="form-check-input mt-1" name="detect_tab_switch" type="checkbox" value="1" @checked(old('detect_tab_switch'))>
                                    <span>
                                        <span class="fw-bold d-block" style="color: var(--psu-navy);">Detect Tab Switch</span>
                                        <span class="small text-secondary">Adds warning when student leaves the assessment tab.</span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-lg-4">
                                <label class="security-box h-100 mb-0">
                                    <input class="form-check-input mt-1" name="screenshot_protection" type="checkbox" value="1" @checked(old('screenshot_protection'))>
                                    <span>
                                        <span class="fw-bold d-block" style="color: var(--psu-navy);">Screenshot Protection</span>
                                        <span class="small text-secondary">Blocks print/screenshot shortcuts when possible and records a warning.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a class="btn btn-outline-secondary px-4" href="{{ route('instructor.assessments') }}">Cancel</a>
                        <button class="btn btn-psu px-4 d-inline-flex align-items-center gap-2" type="submit">
                            <span class="material-symbols-outlined fs-5">publish</span>
                            Publish / Update
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <div class="modal fade" id="publishConfirmModal" tabindex="-1" aria-labelledby="publishConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="material-symbols-outlined fs-2" style="color: var(--psu-navy-2);">publish</span>
                        <div>
                            <h2 class="h4 brand-text mb-1" id="publishConfirmTitle" style="color: var(--psu-navy);">Publish Assessment?</h2>
                            <p class="text-secondary mb-0">
                                If this assessment was already published to the selected class, its schedule and settings will be updated.
                            </p>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Review</button>
                        <button class="btn btn-psu px-4 d-inline-flex align-items-center justify-content-center gap-2" id="confirmPublishAssessment" type="button">
                            <span class="material-symbols-outlined fs-5">check_circle</span>
                            Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('instructor.assessments.assessment-publish-page-code')
@endpush
