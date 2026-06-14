@extends('layouts.portal')

@section('title', 'Teachers | AIssessment Admin/Dean')
@section('header', 'Teachers')

@push('styles')
    <style>
        .stat-card,
        .directory-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .directory-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .table thead th {
            background: #edf2ff;
            color: var(--psu-muted);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 1rem 1.25rem;
        }

        .table tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .avatar {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
            border-radius: 50%;
            font-weight: 800;
            flex-shrink: 0;
        }

        .teachers-table {
            min-width: 1040px;
        }

        .teacher-name {
            margin-bottom: 0;
            color: var(--psu-navy);
            white-space: nowrap;
        }

        .teacher-subtext {
            white-space: nowrap;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy);
        }
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Total Teachers</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalTeachers }}</span>
                    <span class="small text-secondary">Within your college scope</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Admin/Dean Authorized</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalAdminDeans }}</span>
                    <span class="small text-secondary">Teacher accounts with dean access</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Department Chairs</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalDepartmentChairs }}</span>
                    <span class="small text-secondary">Chair-level assignments in scope</span>
                </div>
            </div>
        </div>
    </div>

    @if ($scopedCollege)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            You are viewing teachers under <strong>{{ $scopedCollege->college_name }}</strong>.
        </div>
    @endif

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createInstructorModal" data-bs-toggle="modal" type="button" @disabled($departments->isEmpty())>
            <span class="material-symbols-outlined fs-5">person_add</span>
            Create Instructor
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Teachers List</h3>
            <span class="small text-white-50">Only faculty accounts within your scoped college are shown here</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 teachers-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Employee No.</th>
                        <th>Department</th>
                        <th>Authorization</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teachers as $teacher)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar">{{ strtoupper(substr($teacher->displayName(), 0, 1)) }}</span>
                                    <div>
                                        <p class="fw-bold teacher-name">{{ $teacher->displayName() }}</p>
                                        @if ($teacher->name !== $teacher->displayName())
                                            <p class="small text-secondary mb-0 teacher-subtext">{{ $teacher->name }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $teacher->email }}</td>
                            <td>{{ $teacher->instructorProfile?->employee_number ?? 'Not assigned' }}</td>
                            <td>
                                @if ($teacher->instructorProfile?->department)
                                    <div>
                                        <p class="fw-semibold mb-0">{{ $teacher->instructorProfile->department->dept_name }}</p>
                                        <p class="small text-secondary mb-0">{{ $teacher->instructorProfile->department->college?->college_name }}</p>
                                    </div>
                                @else
                                    <span class="text-secondary fst-italic">Not assigned</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    @if ($teacher->hasRole('admin_dean'))
                                        <span class="badge text-bg-primary rounded-1">Admin/Dean</span>
                                    @endif
                                    @if ($teacher->hasRole('department_chair'))
                                        <span class="badge text-bg-info rounded-1">Department Chair</span>
                                    @endif
                                    @if (! $teacher->hasRole('admin_dean') && ! $teacher->hasRole('department_chair'))
                                        <span class="text-secondary fst-italic">Teacher only</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $teacher->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ ucfirst($teacher->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="6">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">badge</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No teachers found</h4>
                                <p class="text-secondary mb-0">No instructor accounts are currently mapped to this college scope.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $teachers->count() }} {{ $teachers->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Teacher creation is limited to your college departments</span>
        </div>
    </section>

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
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="teacher_password">Password</label>
                            <input class="form-control" id="teacher_password" name="password" required type="password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="teacher_password_confirmation">Confirm Password</label>
                            <input class="form-control" id="teacher_password_confirmation" name="password_confirmation" required type="password">
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
@endsection
