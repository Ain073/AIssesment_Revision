@foreach ($publishedAssessments as $classAssessment)
    <div class="modal fade" id="deletePublishedAssessmentModal{{ $classAssessment->class_assessment_id }}" tabindex="-1" aria-labelledby="deletePublishedAssessmentModalLabel{{ $classAssessment->class_assessment_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('instructor.assessments.published.destroy', $classAssessment) }}" class="modal-content" method="POST" data-ajax-form data-remove-target="#publishedAssessmentCard{{ $classAssessment->class_assessment_id }}">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h3 class="modal-title h4" id="deletePublishedAssessmentModalLabel{{ $classAssessment->class_assessment_id }}">Delete Published Assessment</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $classAssessment->assessment?->title ?? 'Untitled Assessment' }}</p>
                    <p class="text-secondary mb-3">
                        Published to {{ $classAssessment->class?->class_name ?? 'class' }}
                        @if ($classAssessment->due_at)
                            with due date {{ $classAssessment->due_at->format('M d, Y h:i A') }}.
                        @else
                            .
                        @endif
                    </p>
                    <div class="alert alert-warning mb-0">
                        This deletes this published card, including its submissions, results, security events, and reports. The stored draft/template remains available so you can publish again.
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-danger px-4" type="submit">Delete Published</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
