{{-- View, edit, and delete user popups --}}
@foreach ($users as $user)
    <div class="modal fade" id="viewUserModal{{ $user->id }}" tabindex="-1" aria-labelledby="viewUserModalLabel{{ $user->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4 mb-1" id="viewUserModalLabel{{ $user->id }}">User Details</h3>
                        <p class="small text-white-50 mb-0">{{ $user->displayName() }}</p>
                    </div>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-uppercase small">Full Name</label>
                            <div class="form-control bg-light">{{ $user->displayName() }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Email</label>
                            <div class="form-control bg-light">{{ $user->email }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Status</label>
                            <div class="form-control bg-light">{{ ucfirst($user->status) }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Account Type</label>
                            <div class="form-control bg-light">
                                @if ($user->hasRole('super_admin'))
                                    Super Admin
                                @elseif ($user->hasRole('instructor'))
                                    Teacher
                                @elseif ($user->hasRole('student'))
                                    Student
                                @else
                                    Unassigned
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Elevated Access</label>
                            <div class="form-control bg-light">
                                @php
                                    $elevatedAccess = collect([
                                        $user->hasRole('admin_dean') ? 'Admin/Dean' : null,
                                        $user->hasRole('department_chair') ? 'Department Chair' : null,
                                    ])->filter()->implode(', ');
                                @endphp
                                {{ $elevatedAccess !== '' ? $elevatedAccess : 'None' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Department</label>
                            <div class="form-control bg-light">
                                {{ $user->instructorProfile?->department?->dept_name ?? 'Not assigned' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Employee Number</label>
                            <div class="form-control bg-light">
                                {{ $user->instructorProfile?->employee_number ?? 'Not assigned' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Program</label>
                            <div class="form-control bg-light">
                                {{ $user->studentProfile?->program?->program_name ?? 'Not assigned' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Student Number</label>
                            <div class="form-control bg-light">
                                {{ $user->studentProfile?->student_number ?? 'Not assigned' }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                </div>
            </div>
        </div>
    </div>

    @if (! $user->hasRole('super_admin'))
        @php
            $isEditTarget = old('form_mode') === 'edit' && (int) old('user_id') === $user->id;
            $userBaseRole = $user->hasRole('instructor') ? 'instructor' : 'student';
            $editBaseRole = $isEditTarget ? old('base_role') : $userBaseRole;
            $editStatus = $isEditTarget ? old('status') : $user->status;
            $editDepartmentId = $isEditTarget ? old('department_id') : $user->instructorProfile?->department_id;
            $editProgramId = $isEditTarget ? old('program_id') : $user->studentProfile?->program_id;
            $selectedAuthorizations = collect($isEditTarget ? old('authorizations', []) : [
                $user->hasRole('admin_dean') ? 'admin_dean' : null,
                $user->hasRole('department_chair') ? 'department_chair' : null,
            ])->filter()->values();
        @endphp

        <div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1" aria-labelledby="editUserModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form action="{{ route('super-admin.users.update', $user) }}" class="modal-content" method="POST">
                    @csrf
                    @method('PUT')
                    <input name="form_mode" type="hidden" value="edit">
                    <input name="user_id" type="hidden" value="{{ $user->id }}">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="editUserModalLabel{{ $user->id }}">Edit User Account</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-uppercase small" for="edit_first_name_{{ $user->id }}">First Name</label>
                                <input class="form-control" id="edit_first_name_{{ $user->id }}" name="first_name" required type="text" value="{{ $isEditTarget ? old('first_name') : $user->first_name }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-uppercase small" for="edit_middle_name_{{ $user->id }}">Middle Name</label>
                                <input class="form-control" id="edit_middle_name_{{ $user->id }}" name="middle_name" type="text" value="{{ $isEditTarget ? old('middle_name') : $user->middle_name }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-uppercase small" for="edit_last_name_{{ $user->id }}">Last Name</label>
                                <input class="form-control" id="edit_last_name_{{ $user->id }}" name="last_name" required type="text" value="{{ $isEditTarget ? old('last_name') : $user->last_name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small" for="edit_email_{{ $user->id }}">Email</label>
                                <input class="form-control" id="edit_email_{{ $user->id }}" name="email" required type="email" value="{{ $isEditTarget ? old('email') : $user->email }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-uppercase small" for="edit_base_role_{{ $user->id }}">Account Type</label>
                                <select class="form-select" id="edit_base_role_{{ $user->id }}" name="base_role" required>
                                    <option value="instructor" @selected($editBaseRole === 'instructor')>Teacher</option>
                                    <option value="student" @selected($editBaseRole === 'student')>Student</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-uppercase small" for="edit_status_{{ $user->id }}">Status</label>
                                <select class="form-select" id="edit_status_{{ $user->id }}" name="status" required>
                                    <option value="active" @selected($editStatus === 'active')>Active</option>
                                    <option value="inactive" @selected($editStatus === 'inactive')>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6 profile-section teacher-profile-section" id="edit_teacher_department_section_{{ $user->id }}">
                                <label class="form-label fw-bold text-uppercase small" for="edit_department_id_{{ $user->id }}">Department</label>
                                <select class="form-select department-select" id="edit_department_id_{{ $user->id }}" name="department_id">
                                    <option value="">Select department</option>
                                    @forelse ($departments as $department)
                                        <option value="{{ $department->department_id }}" @selected((string) $editDepartmentId === (string) $department->department_id)>
                                            {{ $department->dept_name }} - {{ $department->college?->college_name }}
                                        </option>
                                    @empty
                                        <option value="">No departments available yet</option>
                                    @endforelse
                                </select>
                                <div class="form-text">Required for teacher accounts.</div>
                            </div>
                            <div class="col-md-6 profile-section teacher-profile-section" id="edit_teacher_employee_section_{{ $user->id }}">
                                <label class="form-label fw-bold text-uppercase small" for="edit_employee_number_{{ $user->id }}">Employee Number</label>
                                <input class="form-control teacher-profile-field" id="edit_employee_number_{{ $user->id }}" name="employee_number" type="text" value="{{ $isEditTarget ? old('employee_number') : $user->instructorProfile?->employee_number }}">
                                <div class="form-text">Required for teacher accounts.</div>
                            </div>
                            <div class="col-md-6 profile-section student-profile-section" id="edit_student_program_section_{{ $user->id }}">
                                <label class="form-label fw-bold text-uppercase small" for="edit_program_id_{{ $user->id }}">Program</label>
                                <select class="form-select student-profile-field" id="edit_program_id_{{ $user->id }}" name="program_id">
                                    <option value="">Select program</option>
                                    @forelse ($programs as $program)
                                        <option value="{{ $program->program_id }}" @selected((string) $editProgramId === (string) $program->program_id)>
                                            {{ $program->program_name }} - {{ $program->college?->college_name }}
                                        </option>
                                    @empty
                                        <option value="">No programs available yet</option>
                                    @endforelse
                                </select>
                                <div class="form-text">Required for student accounts.</div>
                            </div>
                            <div class="col-md-6 profile-section student-profile-section" id="edit_student_number_section_{{ $user->id }}">
                                <label class="form-label fw-bold text-uppercase small" for="edit_student_number_{{ $user->id }}">Student Number</label>
                                <input class="form-control student-profile-field" id="edit_student_number_{{ $user->id }}" name="student_number" type="text" value="{{ $isEditTarget ? old('student_number') : $user->studentProfile?->student_number }}">
                                <div class="form-text">Required for student accounts.</div>
                            </div>
                            <div class="col-12 profile-section teacher-profile-section" id="edit_authorization_section_{{ $user->id }}">
                                <div class="border rounded p-3" style="background: #eff4ff;">
                                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                        <div>
                                            <p class="small fw-bold text-uppercase text-secondary mb-1">Authorization</p>
                                            <p class="small text-secondary mb-0">Teacher accounts can receive elevated access here.</p>
                                        </div>
                                        <span class="badge text-bg-light border">Teachers only</span>
                                    </div>
                                    <div class="d-grid gap-3">
                                        <label class="border rounded p-3 d-flex align-items-start gap-3 bg-white">
                                            <input class="form-check-input mt-1 authorization-checkbox" data-teacher-target="edit_base_role_{{ $user->id }}" name="authorizations[]" type="checkbox" value="admin_dean" @checked($selectedAuthorizations->contains('admin_dean'))>
                                            <span>
                                                <span class="fw-bold d-block" style="color: var(--psu-navy);">Admin/Dean</span>
                                                <span class="small text-secondary">Assign dean-level access to this teacher account.</span>
                                            </span>
                                        </label>
                                        <label class="border rounded p-3 d-flex align-items-start gap-3 bg-white">
                                            <input class="form-check-input mt-1 authorization-checkbox" data-teacher-target="edit_base_role_{{ $user->id }}" name="authorizations[]" type="checkbox" value="department_chair" @checked($selectedAuthorizations->contains('department_chair'))>
                                            <span>
                                                <span class="fw-bold d-block" style="color: var(--psu-navy);">Department Chair</span>
                                                <span class="small text-secondary">Assign chair-level access to this teacher account.</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-light border mb-0">
                                    Password is managed by the account owner.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="deleteUserModal{{ $user->id }}" tabindex="-1" aria-labelledby="deleteUserModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('super-admin.users.destroy', $user) }}" class="modal-content" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="deleteUserModalLabel{{ $user->id }}">Delete User</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">You are about to delete this account:</p>
                        <div class="border rounded p-3" style="background: #eff4ff;">
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $user->displayName() }}</p>
                            <p class="small text-secondary mb-0">{{ $user->email }}</p>
                        </div>
                        <p class="text-danger small mt-3 mb-0">This action will remove the user account from the system.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger px-4" type="submit">Confirm Delete</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
