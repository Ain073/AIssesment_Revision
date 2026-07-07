{{-- Create instructor form --}}
<div class="modal fade" id="createInstructorModal" tabindex="-1" aria-labelledby="createInstructorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ route('super-admin.users.store') }}" class="modal-content" method="POST">
            @csrf
            <input name="form_mode" type="hidden" value="create">
            <input name="base_role" type="hidden" value="instructor">
            <div class="modal-header">
                <h3 class="modal-title h4" id="createInstructorModalLabel">Create Instructor Account</h3>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_first_name">First Name</label>
                        <input class="form-control" id="instructor_first_name" name="first_name" required type="text" value="{{ old('base_role') === 'instructor' ? old('first_name') : '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_middle_name">Middle Name</label>
                        <input class="form-control" id="instructor_middle_name" name="middle_name" type="text" value="{{ old('base_role') === 'instructor' ? old('middle_name') : '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_last_name">Last Name</label>
                        <input class="form-control" id="instructor_last_name" name="last_name" required type="text" value="{{ old('base_role') === 'instructor' ? old('last_name') : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_email">Email</label>
                        <input class="form-control" id="instructor_email" name="email" required type="email" value="{{ old('base_role') === 'instructor' ? old('email') : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_status">Status</label>
                        <select class="form-select" id="instructor_status" name="status" required>
                            <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_department_id">Department</label>
                        <select class="form-select department-select" id="instructor_department_id" name="department_id" required>
                            <option value="">Select department</option>
                            @forelse ($departments as $department)
                                <option value="{{ $department->department_id }}" @selected((string) old('department_id') === (string) $department->department_id)>
                                    {{ $department->dept_name }} - {{ $department->college?->college_name }}
                                </option>
                            @empty
                                <option value="">No departments available yet</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_employee_number">Employee Number</label>
                        <input class="form-control teacher-profile-field" id="instructor_employee_number" name="employee_number" required type="text" value="{{ old('base_role') === 'instructor' ? old('employee_number') : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_password">Password</label>
                        <input class="form-control" id="instructor_password" name="password" required type="password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="instructor_password_confirmation">Confirm Password</label>
                        <input class="form-control" id="instructor_password_confirmation" name="password_confirmation" required type="password">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                <button class="btn btn-psu px-4" type="submit">Create Instructor</button>
            </div>
        </form>
    </div>
</div>
