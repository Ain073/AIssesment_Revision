{{-- Create instructor form --}}
<div class="modal fade" id="createInstructorModal" tabindex="-1" aria-labelledby="createInstructorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ route('admin-dean.users.store') }}" class="modal-content" method="POST">
            @csrf
            <input name="base_role" type="hidden" value="instructor">

            <div class="modal-header">
                <h3 class="modal-title h4" id="createInstructorModalLabel">Create Instructor Account</h3>
                <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="teacher_first_name">First Name</label>
                        <input class="form-control" id="teacher_first_name" name="first_name" required type="text" value="{{ old('base_role') === 'instructor' ? old('first_name') : '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="teacher_middle_name">Middle Name</label>
                        <input class="form-control" id="teacher_middle_name" name="middle_name" type="text" value="{{ old('base_role') === 'instructor' ? old('middle_name') : '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-uppercase small" for="teacher_last_name">Last Name</label>
                        <input class="form-control" id="teacher_last_name" name="last_name" required type="text" value="{{ old('base_role') === 'instructor' ? old('last_name') : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="teacher_email">Email</label>
                        <input class="form-control" id="teacher_email" name="email" required type="email" value="{{ old('base_role') === 'instructor' ? old('email') : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="teacher_status">Status</label>
                        <select class="form-select" id="teacher_status" name="status" required>
                            <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="department_id">Department</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->department_id }}" @selected((string) old('department_id') === (string) $department->department_id)>
                                    {{ $department->dept_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase small" for="employee_number">Employee Number</label>
                        <input class="form-control" id="employee_number" name="employee_number" required type="text" value="{{ old('base_role') === 'instructor' ? old('employee_number') : '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-uppercase small d-block">Designation</label>
                        <div class="form-check">
                            <input class="form-check-input" id="create_teacher_department_chair" name="authorizations[]" type="checkbox" value="department_chair" @checked(old('base_role') === 'instructor' && in_array('department_chair', old('authorizations', []), true))>
                            <label class="form-check-label fw-semibold" for="create_teacher_department_chair">Department Chair</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-psu px-4" type="submit">Create Instructor</button>
            </div>
        </form>
    </div>
</div>
