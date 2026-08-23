@php
    $accountUsers = collect($accountUsers ?? [])->filter();
    $accountRoutePrefix = $accountRoutePrefix ?? '';
    $accountMode = $accountMode ?? 'student';
    $accountDepartments = collect($accountDepartments ?? []);
    $accountPrograms = collect($accountPrograms ?? []);
    $accountFixedDepartment = $accountFixedDepartment ?? null;
    $accountAllowDepartmentSelect = (bool) ($accountAllowDepartmentSelect ?? false);
@endphp

@foreach ($accountUsers as $accountUser)
    @php
        $isInstructorAccount = $accountMode === 'instructor';
        $isStudentAccount = $accountMode === 'student';
        $isEditTarget = old('form_mode') === 'edit' && (int) old('user_id') === $accountUser->id;
        $editStatus = $isEditTarget ? old('status') : $accountUser->status;
        $editDepartmentId = $isEditTarget ? old('department_id') : $accountUser->instructorProfile?->department_id;
        $editProgramId = $isEditTarget ? old('program_id') : $accountUser->studentProfile?->program_id;
        $deleteDisabled = (int) auth()->id() === (int) $accountUser->id;
        $authorization = collect([
            $accountUser->hasRole('admin_dean') ? 'Admin/Dean' : null,
            $accountUser->hasRole('department_chair') ? 'Department Chair' : null,
        ])->filter()->implode(', ');
    @endphp

    <div class="modal fade" id="viewAccountModal{{ $accountUser->id }}" tabindex="-1" aria-labelledby="viewAccountModalLabel{{ $accountUser->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4 mb-1" id="viewAccountModalLabel{{ $accountUser->id }}">{{ $isInstructorAccount ? 'Teacher Details' : 'Student Details' }}</h3>
                        <p class="small text-white-50 mb-0">{{ $accountUser->displayName() }}</p>
                    </div>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-uppercase small">Full Name</label>
                            <div class="form-control bg-light">{{ $accountUser->displayName() }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Email</label>
                            <div class="form-control bg-light">{{ $accountUser->email }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Status</label>
                            <div class="form-control bg-light">{{ ucfirst($accountUser->status) }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small">Account Type</label>
                            <div class="form-control bg-light">{{ $isInstructorAccount ? 'Teacher' : 'Student' }}</div>
                        </div>
                        @if ($isInstructorAccount)
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Authorization</label>
                                <div class="form-control bg-light">{{ $authorization !== '' ? $authorization : 'Teacher only' }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Department</label>
                                <div class="form-control bg-light">{{ $accountUser->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Employee Number</label>
                                <div class="form-control bg-light">{{ $accountUser->instructorProfile?->employee_number ?? 'Not assigned' }}</div>
                            </div>
                        @endif
                        @if ($isStudentAccount)
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Program</label>
                                <div class="form-control bg-light">{{ $accountUser->studentProfile?->program?->program_name ?? 'Not assigned' }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small">Student Number</label>
                                <div class="form-control bg-light">{{ $accountUser->studentProfile?->student_number ?? 'Not assigned' }}</div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                    <button class="btn btn-outline-psu px-4" data-bs-target="#editAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button">Edit</button>
                    <button class="btn btn-danger px-4" data-bs-target="#deleteAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button" @disabled($deleteDisabled)>Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAccountModal{{ $accountUser->id }}" tabindex="-1" aria-labelledby="editAccountModalLabel{{ $accountUser->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form action="{{ route($accountRoutePrefix.'.users.update', $accountUser) }}" class="modal-content" method="POST">
                @csrf
                @method('PUT')
                <input name="form_mode" type="hidden" value="edit">
                <input name="user_id" type="hidden" value="{{ $accountUser->id }}">
                <input name="base_role" type="hidden" value="{{ $accountMode }}">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="editAccountModalLabel{{ $accountUser->id }}">Edit {{ $isInstructorAccount ? 'Teacher' : 'Student' }}</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="account_first_name_{{ $accountUser->id }}">First Name</label>
                            <input class="form-control" id="account_first_name_{{ $accountUser->id }}" name="first_name" required type="text" value="{{ $isEditTarget ? old('first_name') : $accountUser->first_name }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="account_middle_name_{{ $accountUser->id }}">Middle Name</label>
                            <input class="form-control" id="account_middle_name_{{ $accountUser->id }}" name="middle_name" type="text" value="{{ $isEditTarget ? old('middle_name') : $accountUser->middle_name }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="account_last_name_{{ $accountUser->id }}">Last Name</label>
                            <input class="form-control" id="account_last_name_{{ $accountUser->id }}" name="last_name" required type="text" value="{{ $isEditTarget ? old('last_name') : $accountUser->last_name }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-uppercase small" for="account_email_{{ $accountUser->id }}">Email</label>
                            <input class="form-control" id="account_email_{{ $accountUser->id }}" name="email" required type="email" value="{{ $isEditTarget ? old('email') : $accountUser->email }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="account_status_{{ $accountUser->id }}">Status</label>
                            <select class="form-select" id="account_status_{{ $accountUser->id }}" name="status" required>
                                <option value="active" @selected($editStatus === 'active')>Active</option>
                                <option value="inactive" @selected($editStatus === 'inactive')>Inactive</option>
                            </select>
                        </div>

                        @if ($isInstructorAccount)
                            @if ($accountAllowDepartmentSelect)
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-uppercase small" for="account_department_{{ $accountUser->id }}">Department</label>
                                    <select class="form-select" id="account_department_{{ $accountUser->id }}" name="department_id" required>
                                        <option value="">Select department</option>
                                        @foreach ($accountDepartments as $department)
                                            <option value="{{ $department->department_id }}" @selected((string) $editDepartmentId === (string) $department->department_id)>
                                                {{ $department->dept_name }} - {{ $department->college?->college_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <input name="department_id" type="hidden" value="{{ $accountFixedDepartment?->department_id ?? $accountUser->instructorProfile?->department_id }}">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-uppercase small">Department</label>
                                    <div class="form-control bg-light">{{ $accountFixedDepartment?->dept_name ?? $accountUser->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</div>
                                </div>
                            @endif
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small" for="account_employee_number_{{ $accountUser->id }}">Employee Number</label>
                                <input class="form-control" id="account_employee_number_{{ $accountUser->id }}" name="employee_number" required type="text" value="{{ $isEditTarget ? old('employee_number') : $accountUser->instructorProfile?->employee_number }}">
                            </div>
                        @endif

                        @if ($isStudentAccount)
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small" for="account_program_{{ $accountUser->id }}">Program</label>
                                <select class="form-select" id="account_program_{{ $accountUser->id }}" name="program_id" required>
                                    <option value="">Select program</option>
                                    @foreach ($accountPrograms as $program)
                                        <option value="{{ $program->program_id }}" @selected((string) $editProgramId === (string) $program->program_id)>
                                            {{ $program->program_name }} - {{ $program->college?->college_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-uppercase small" for="account_student_number_{{ $accountUser->id }}">Student Number</label>
                                <input class="form-control" id="account_student_number_{{ $accountUser->id }}" name="student_number" required type="text" value="{{ $isEditTarget ? old('student_number') : $accountUser->studentProfile?->student_number }}">
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteAccountModal{{ $accountUser->id }}" tabindex="-1" aria-labelledby="deleteAccountModalLabel{{ $accountUser->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route($accountRoutePrefix.'.users.destroy', $accountUser) }}" class="modal-content" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h3 class="modal-title h4" id="deleteAccountModalLabel{{ $accountUser->id }}">Delete {{ $isInstructorAccount ? 'Teacher' : 'Student' }}</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($deleteDisabled)
                        <div class="alert alert-warning mb-0">You cannot delete your own account.</div>
                    @else
                        <p class="mb-2">You are about to delete this account:</p>
                        <div class="border rounded p-3" style="background: #eff4ff;">
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $accountUser->displayName() }}</p>
                            <p class="small text-secondary mb-0">{{ $accountUser->email }}</p>
                        </div>
                        <p class="text-danger small mt-3 mb-0">This will remove the account from this portal scope.</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-danger px-4" type="submit" @disabled($deleteDisabled)>Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
