@foreach ($publishedAssessments as $publishAssessment)
    @if (($publishAssessment->display_status ?? null) === 'completed')
        @php($assessment = $publishAssessment->assessment)
        @php($class = $publishAssessment->classDetail?->class)
        <div class="modal fade" id="reopenPublishedAssessmentModal{{ $publishAssessment->publish_assessment_id }}" tabindex="-1" aria-labelledby="reopenPublishedAssessmentModalLabel{{ $publishAssessment->publish_assessment_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('instructor.assessments.published.reopen', $publishAssessment) }}" class="modal-content" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title h4" id="reopenPublishedAssessmentModalLabel{{ $publishAssessment->publish_assessment_id }}">Reopen Assessment</h3>
                            <p class="small text-white-50 mb-0">{{ $assessment?->title ?? 'Untitled Assessment' }} - {{ $class?->class_name ?? 'Class' }}</p>
                        </div>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-secondary">
                            This keeps existing submissions and only lets students without a submitted attempt take the assessment.
                        </p>
                        @if ($publishAssessment->due_at)
                            <p class="small text-secondary mb-3">Original due date: {{ ($publishAssessment->original_due_at ?? $publishAssessment->due_at)->format('M d, Y h:i A') }}</p>
                        @endif
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="reopen_available_at_{{ $publishAssessment->publish_assessment_id }}">Available At</label>
                            <input class="form-control" id="reopen_available_at_{{ $publishAssessment->publish_assessment_id }}" name="available_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}">
                            <div class="form-text">Leave as current time if students should access it immediately.</div>
                        </div>
                        <div>
                            <label class="form-label fw-bold text-uppercase small" for="reopen_due_at_{{ $publishAssessment->publish_assessment_id }}">New Due Date</label>
                            <input class="form-control" id="reopen_due_at_{{ $publishAssessment->publish_assessment_id }}" name="due_at" required type="datetime-local" value="{{ now()->addDay()->format('Y-m-d\TH:i') }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-psu d-inline-flex align-items-center gap-2 px-4" type="submit">
                            <span class="material-symbols-outlined fs-5">restart_alt</span>
                            Reopen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
