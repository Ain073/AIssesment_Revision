@foreach ($assessments->where('status', \App\Models\Assessment::STATUS_DRAFT) as $assessment)
    <div class="modal fade" id="deleteAssessmentModal{{ $assessment->assessment_id }}" tabindex="-1" aria-labelledby="deleteAssessmentModalLabel{{ $assessment->assessment_id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('instructor.assessments.destroy', $assessment) }}" class="modal-content" method="POST" data-ajax-form data-remove-target="#assessmentCard{{ $assessment->assessment_id }}">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h3 class="modal-title h4" id="deleteAssessmentModalLabel{{ $assessment->assessment_id }}">Delete Draft Assessment</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-bold mb-2" style="color: var(--psu-navy);">{{ $assessment->title }}</p>
                    <p class="text-secondary mb-0">This will remove the draft assessment and its saved questions.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-danger px-4" type="submit">Delete Draft</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
