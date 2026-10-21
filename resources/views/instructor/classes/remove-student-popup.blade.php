<div class="modal fade" id="removeClassStudentModal" tabindex="-1" aria-labelledby="removeClassStudentModalLabel" aria-describedby="removeClassStudentDescription" aria-hidden="true" data-remove-class-student-modal>
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" method="POST" data-ajax-form data-submitting-text="Removing...">
            @csrf
            @method('DELETE')
            <div class="modal-header px-4 py-3">
                <h3 class="modal-title h4 mb-0" id="removeClassStudentModalLabel">Remove Student</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-start gap-3 mb-4">
                    <span class="empty-icon flex-shrink-0 text-danger" style="background: #fff1f2;" aria-hidden="true">
                        <span class="material-symbols-outlined fs-2">person_remove</span>
                    </span>
                    <div>
                        <h4 class="h5 mb-2" style="color: var(--psu-navy);">Remove from this class?</h4>
                        <p class="small text-secondary mb-0" id="removeClassStudentDescription">The student will lose access to this class. Their student account will remain available.</p>
                    </div>
                </div>
                <div class="border rounded-3 p-3" style="background: #f8faff;">
                    <div class="small fw-bold text-secondary text-uppercase mb-1">Student</div>
                    <div class="fw-bold text-break" style="color: var(--psu-navy);" data-removal-student-name></div>
                    <div class="small text-secondary" data-removal-student-number></div>
                    <div class="border-top mt-3 pt-3">
                        <div class="small fw-bold text-secondary text-uppercase mb-1">Class</div>
                        <div class="fw-semibold" style="color: var(--psu-navy);">{{ $class->displayName() }}</div>
                    </div>
                </div>
                <div class="alert alert-danger d-none mt-3 mb-0" data-ajax-errors role="alert"></div>
            </div>
            <div class="modal-footer bg-light px-4">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-danger px-4 d-inline-flex align-items-center justify-content-center gap-2" type="submit" data-confirm-student-removal disabled>
                    <span class="material-symbols-outlined fs-5" aria-hidden="true">person_remove</span>
                    Remove Student
                </button>
            </div>
        </form>
    </div>
</div>
