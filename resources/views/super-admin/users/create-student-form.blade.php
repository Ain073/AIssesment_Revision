{{-- Create student form --}}
<div class="modal fade" id="createStudentModal" tabindex="-1" aria-labelledby="createStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ route('super-admin.users.store') }}" class="modal-content" method="POST">
            @csrf
            <input name="form_mode" type="hidden" value="create">
            <input name="base_role" type="hidden" value="student">
            <div class="modal-header">
                <h3 class="modal-title h4" id="createStudentModalLabel">Create Student Account</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="student-create-mode" role="group" aria-label="Student account creation method">
                    <button class="btn active" type="button" aria-current="true">
                        <span class="material-symbols-outlined fs-5">person</span>
                        Manual Entry
                    </button>
                    <button class="btn" data-bs-target="#importStudentsModal" data-bs-toggle="modal" type="button">
                        <span class="material-symbols-outlined fs-5">upload_file</span>
                        Import File
                    </button>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="student_first_name">First Name</label>
                        <input class="form-control" id="student_first_name" name="first_name" required type="text" value="{{ old('base_role') === 'student' ? old('first_name') : '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="student_middle_name">Middle Name</label>
                        <input class="form-control" id="student_middle_name" name="middle_name" type="text" value="{{ old('base_role') === 'student' ? old('middle_name') : '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="student_last_name">Last Name</label>
                        <input class="form-control" id="student_last_name" name="last_name" required type="text" value="{{ old('base_role') === 'student' ? old('last_name') : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="student_email">Email</label>
                        <input class="form-control" id="student_email" name="email" required type="email" value="{{ old('base_role') === 'student' ? old('email') : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="student_status">Status</label>
                        <select class="form-select" id="student_status" name="status" required>
                            <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="student_program_id">Program</label>
                        <select class="form-select student-profile-field" id="student_program_id" name="program_id" required>
                            <option value="">Select program</option>
                            @forelse ($programs as $program)
                                <option value="{{ $program->program_id }}" @selected((string) old('program_id') === (string) $program->program_id)>
                                    {{ $program->program_name }} - {{ $program->department?->dept_name }} - {{ $program->department?->college?->college_name }}
                                </option>
                            @empty
                                <option value="">No programs available yet</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="student_number">Student Number</label>
                        <input class="form-control student-profile-field" id="student_number" name="student_number" required type="text" value="{{ old('base_role') === 'student' ? old('student_number') : '' }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                <button class="btn btn-psu px-4" type="submit">Create Student</button>
            </div>
        </form>
    </div>
</div>
