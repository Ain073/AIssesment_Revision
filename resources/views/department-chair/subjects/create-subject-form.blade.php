    {{-- Create subject form --}}
    @php
        $subjectRoutePrefix = $subjectRoutePrefix ?? 'department-chair';
    @endphp
    <div class="modal fade" id="subjectModal" tabindex="-1" aria-labelledby="subjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form
                action="{{ route($subjectRoutePrefix.'.subjects.store') }}"
                class="modal-content"
                method="POST"
                data-ajax-form
                data-reset-on-success="true"
            >
                @csrf
                <input name="is_active" type="hidden" value="0">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="subjectModalLabel">New Subject</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small">Department</label>
                        <div class="form-control form-control-lg bg-light">{{ $scopedDepartment?->dept_name ?? 'No department assigned' }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="subject_code">Subject Code</label>
                        <input class="form-control form-control-lg" id="subject_code" name="subject_code" placeholder="e.g. IT 101" required type="text" value="{{ old('subject_code') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="subject_name">Subject Name</label>
                        <input class="form-control form-control-lg" id="subject_name" name="subject_name" placeholder="e.g. Introduction to Computing" required type="text" value="{{ old('subject_name') }}">
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                        <label class="form-check-label fw-semibold" for="is_active">Subject is enabled</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled(! $scopedDepartment)>Save Subject</button>
                </div>
            </form>
        </div>
    </div>
