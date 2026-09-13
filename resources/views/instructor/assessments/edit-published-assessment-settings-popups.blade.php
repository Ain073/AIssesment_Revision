@foreach ($publishedAssessments as $publishAssessment)
    @php($assessment = $publishAssessment->assessment)
    @php($class = $publishAssessment->classDetail?->class)
    <div class="modal fade" id="editPublishedAssessmentSettingsModal{{ $publishAssessment->publish_assessment_id }}" tabindex="-1" aria-labelledby="editPublishedAssessmentSettingsModalLabel{{ $publishAssessment->publish_assessment_id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form action="{{ route('instructor.assessments.published.settings.update', $publishAssessment) }}" class="modal-content" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4" id="editPublishedAssessmentSettingsModalLabel{{ $publishAssessment->publish_assessment_id }}">Edit Publish Settings</h3>
                        <p class="small text-white-50 mb-0">{{ $assessment?->title ?? 'Untitled Assessment' }} - {{ $class?->class_name ?? 'Class' }}</p>
                    </div>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0">
                        Assessment questions stay locked. These settings only adjust access, visibility, attempts, and security.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="settings_available_at_{{ $publishAssessment->publish_assessment_id }}">Available At</label>
                            <input class="form-control" id="settings_available_at_{{ $publishAssessment->publish_assessment_id }}" name="available_at" type="datetime-local" value="{{ $publishAssessment->available_at?->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="settings_due_at_{{ $publishAssessment->publish_assessment_id }}">Due At</label>
                            <input class="form-control" id="settings_due_at_{{ $publishAssessment->publish_assessment_id }}" name="due_at" type="datetime-local" value="{{ $publishAssessment->due_at?->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="settings_attempt_limit_{{ $publishAssessment->publish_assessment_id }}">Attempt Limit</label>
                            <input class="form-control" id="settings_attempt_limit_{{ $publishAssessment->publish_assessment_id }}" max="10" min="1" name="attempt_limit" required type="number" value="{{ $publishAssessment->attempt_limit }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="settings_warning_limit_{{ $publishAssessment->publish_assessment_id }}">Warning Limit</label>
                            <input class="form-control" id="settings_warning_limit_{{ $publishAssessment->publish_assessment_id }}" max="20" min="0" name="warning_limit" type="number" value="{{ $publishAssessment->warning_limit }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="settings_display_mode_{{ $publishAssessment->publish_assessment_id }}">Question Display</label>
                            <select class="form-select" id="settings_display_mode_{{ $publishAssessment->publish_assessment_id }}" name="display_mode" required>
                                <option value="{{ \App\Models\PublishAssessment::DISPLAY_ALL_QUESTIONS }}" @selected($publishAssessment->display_mode === \App\Models\PublishAssessment::DISPLAY_ALL_QUESTIONS)>Show all questions</option>
                                <option value="{{ \App\Models\PublishAssessment::DISPLAY_ONE_QUESTION }}" @selected($publishAssessment->display_mode === \App\Models\PublishAssessment::DISPLAY_ONE_QUESTION)>One question at a time</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check border rounded p-3 h-100 mb-0">
                                <input class="form-check-input" name="score_visibility" type="checkbox" value="1" @checked($publishAssessment->score_visibility)>
                                <span class="fw-bold ms-1" style="color: var(--psu-navy);">Show Scores</span>
                            </label>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check border rounded p-3 h-100 mb-0">
                                <input class="form-check-input" name="answer_visibility" type="checkbox" value="1" @checked($publishAssessment->answer_visibility)>
                                <span class="fw-bold ms-1" style="color: var(--psu-navy);">Show Answers</span>
                            </label>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check border rounded p-3 h-100 mb-0">
                                <input class="form-check-input" name="shuffle_items" type="checkbox" value="1" @checked($publishAssessment->shuffle_items)>
                                <span class="fw-bold ms-1" style="color: var(--psu-navy);">Shuffle Items</span>
                            </label>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check border rounded p-3 h-100 mb-0">
                                <input class="form-check-input" name="shuffle_choices" type="checkbox" value="1" @checked($publishAssessment->shuffle_choices)>
                                <span class="fw-bold ms-1" style="color: var(--psu-navy);">Shuffle Choices</span>
                            </label>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check border rounded p-3 h-100 mb-0">
                                <input class="form-check-input" name="prevent_copy_paste" type="checkbox" value="1" @checked($publishAssessment->prevent_copy_paste)>
                                <span class="fw-bold ms-1" style="color: var(--psu-navy);">No Copy / Paste</span>
                            </label>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check border rounded p-3 h-100 mb-0">
                                <input class="form-check-input" name="detect_tab_switch" type="checkbox" value="1" @checked($publishAssessment->detect_tab_switch)>
                                <span class="fw-bold ms-1" style="color: var(--psu-navy);">Detect Tab Switch</span>
                            </label>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-check border rounded p-3 h-100 mb-0">
                                <input class="form-check-input" name="screenshot_protection" type="checkbox" value="1" @checked($publishAssessment->screenshot_protection)>
                                <span class="fw-bold ms-1" style="color: var(--psu-navy);">Screenshot Protection</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu d-inline-flex align-items-center gap-2 px-4" type="submit">
                        <span class="material-symbols-outlined fs-5">save</span>
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
@endforeach
