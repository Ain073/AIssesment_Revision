<div class="modal fade" id="joinLinkModal" tabindex="-1" aria-labelledby="joinLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h4" id="joinLinkModalLabel">Class Join Code</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-bold text-uppercase small" for="classJoinCode">Share this code with students</label>
                <div class="input-group mb-3">
                    <input class="form-control join-code-field" id="classJoinCode" readonly type="text" value="{{ $class->join_code }}">
                    <button class="btn btn-outline-primary d-inline-flex align-items-center gap-1" data-copy-target="classJoinCode" type="button">
                        <span class="material-symbols-outlined fs-6">content_copy</span>
                        <span class="copy-label">Copy</span>
                    </button>
                </div>

                <label class="form-label fw-bold text-uppercase small" for="classJoinLink">Optional join link</label>
                <div class="input-group">
                    <input class="form-control join-link-field" id="classJoinLink" readonly type="text" value="{{ $classJoinLink }}">
                    <button class="btn btn-outline-primary d-inline-flex align-items-center gap-1" data-copy-target="classJoinLink" type="button">
                        <span class="material-symbols-outlined fs-6">content_copy</span>
                        <span class="copy-label">Copy</span>
                    </button>
                </div>
                <div class="alert alert-warning border-0 mt-3 mb-2">
                    Use the class code for now. The join link will only be final after the system is uploaded online.
                </div>
                <div class="alert alert-primary border-0 mb-0">
                    Students who use the code or link will only send a request. They will not be enrolled until you approve them.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
            </div>
        </div>
    </div>
</div>
