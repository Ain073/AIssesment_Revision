{{-- Create college form --}}
<div class="modal fade" id="collegeModal" tabindex="-1" aria-labelledby="collegeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('super-admin.colleges.store') }}" class="modal-content" method="POST">
            @csrf
            <div class="modal-header">
                <h3 class="modal-title h4" id="collegeModalLabel">New College</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-bold text-uppercase small" for="college_name">College Name</label>
                <input class="form-control form-control-lg" id="college_name" name="college_name" placeholder="e.g. College of Information Technology" required type="text" value="{{ old('college_name') }}">
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                <button class="btn btn-psu px-4" type="submit">Save College</button>
            </div>
        </form>
    </div>
</div>
