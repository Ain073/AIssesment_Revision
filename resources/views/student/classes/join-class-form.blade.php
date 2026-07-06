<div class="modal fade" id="joinClassModal" tabindex="-1" aria-labelledby="joinClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('student.classes.join-code.request') }}" method="POST" class="modal-content" data-ajax-form data-reset-on-success="true">
            @csrf
            <div class="modal-header">
                <h3 class="modal-title h4" id="joinClassModalLabel">Join Class</h3>
                <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <label class="form-label fw-bold text-uppercase small" for="join_code">Class Code</label>
                <input class="form-control form-control-lg join-code-input" id="join_code" name="join_code" placeholder="ABC123" required type="text" value="{{ old('join_code') }}" maxlength="20">
                <div class="form-text">Enter the code shared by your teacher. Your request still needs teacher approval.</div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-psu px-4 d-inline-flex align-items-center gap-2" type="submit">
                    <span class="material-symbols-outlined fs-5">send</span>
                    Send Request
                </button>
            </div>
        </form>
    </div>
</div>
