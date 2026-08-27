<div class="modal fade" id="classModal" tabindex="-1" aria-labelledby="classModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('instructor.classes.store') }}" class="modal-content" method="POST" data-ajax-form data-reset-on-success="true">
            @csrf
            <div class="modal-header">
                <h3 class="modal-title h4" id="classModalLabel">New Class</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold text-uppercase small" for="subject_id">Subject</label>
                    <select class="form-select form-select-lg" id="subject_id" name="subject_id" required>
                        <option value="">Select subject</option>
                        @foreach ($activeSubjects as $subject)
                            <option value="{{ $subject->subject_id }}" @selected(old('subject_id') == $subject->subject_id)>
                                {{ $subject->subject_code }} - {{ $subject->subject_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-uppercase small" for="year_level">Year Level</label>
                        <select class="form-select form-select-lg" id="year_level" name="year_level" required>
                            @foreach ([1, 2, 3, 4] as $yearLevel)
                                <option value="{{ $yearLevel }}" @selected((string) old('year_level', '1') === (string) $yearLevel)>Year {{ $yearLevel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold text-uppercase small" for="section_name">Section Name</label>
                        <input class="form-control form-control-lg" id="section_name" name="section_name" placeholder="e.g. BSIT 3A" required type="text" value="{{ old('section_name') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                <button class="btn btn-psu px-4" type="submit">Save Class</button>
            </div>
        </form>
    </div>
</div>
