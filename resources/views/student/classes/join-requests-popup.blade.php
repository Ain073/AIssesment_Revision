<div class="modal fade" id="joinRequestsModal" tabindex="-1" aria-labelledby="joinRequestsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title h4" id="joinRequestsModalLabel">Join Requests</h3>
                </div>
                <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0" data-poll-url="{{ route('student.classes.requests.live') }}" data-poll-interval="5000">
                @include('student.classes.join-request-list')
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
            </div>
        </div>
    </div>
</div>
