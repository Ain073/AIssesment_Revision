@extends('layouts.portal')

@section('title', 'Users | AIssessment Department Chair')
@section('header', 'Users')

@push('styles')
    <style>
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
            text-align: center;
            padding: 1rem 0.75rem;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .avatar,
        .empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
        }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            font-weight: 800;
            flex-shrink: 0;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
        }

        .users-table {
            width: 100%;
            min-width: 0;
            table-layout: fixed;
        }

        .users-table th,
        .users-table td {
            overflow-wrap: anywhere;
        }

        .teacher-name {
            margin-bottom: 0;
            color: var(--psu-navy);
            line-height: 1.35;
            white-space: normal;
        }

        .teacher-subtext {
            white-space: normal;
        }
    </style>
@endpush

@push('scripts')
    @include('department-chair.students.student-page-code')
    @if (request('action') === 'create-teacher')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('createInstructorModal');

                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('mail_warning'))
        <div class="alert alert-warning">{{ session('mail_warning') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if (! $scopedDepartment)
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            This account has no assigned department yet, so user scope cannot be resolved.
        </div>
    @endif

    <div data-table-tabs-root data-table-tabs-param="tab" data-table-tabs-default="{{ $activeUserTab }}">
        <div class="table-switch-tabs mb-4" role="tablist" aria-label="User directory views">
            <button class="btn btn-outline-primary table-switch-button {{ $activeUserTab === 'teachers' ? 'active' : '' }} d-inline-flex align-items-center gap-2" data-table-tab-button="teachers" type="button" role="tab" aria-pressed="{{ $activeUserTab === 'teachers' ? 'true' : 'false' }}">
                <span class="material-symbols-outlined fs-5">badge</span>
                Teachers
                <span class="table-switch-count">{{ $teachers->count() }}</span>
            </button>
            <button class="btn btn-outline-primary table-switch-button {{ $activeUserTab === 'students' ? 'active' : '' }} d-inline-flex align-items-center gap-2" data-table-tab-button="students" type="button" role="tab" aria-pressed="{{ $activeUserTab === 'students' ? 'true' : 'false' }}">
                <span class="material-symbols-outlined fs-5">groups</span>
                Students
                <span class="table-switch-count">{{ $students->count() }}</span>
            </button>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
            <button class="btn btn-psu d-inline-flex align-items-center gap-2 {{ $activeUserTab === 'teachers' ? '' : 'd-none' }}" data-table-tab-panel="teachers" data-bs-target="#createInstructorModal" data-bs-toggle="modal" type="button" @disabled(! $scopedDepartment) @if ($activeUserTab !== 'teachers') hidden @endif>
                <span class="material-symbols-outlined fs-5">add</span>
                Instructor
            </button>
            <button class="btn btn-psu d-inline-flex align-items-center gap-2 {{ $activeUserTab === 'students' ? '' : 'd-none' }}" data-table-tab-panel="students" data-bs-target="#createStudentModal" data-bs-toggle="modal" type="button" @disabled($scopedPrograms->isEmpty()) @if ($activeUserTab !== 'students') hidden @endif>
                <span class="material-symbols-outlined fs-5">add</span>
                Student
            </button>
        </div>

        <section class="directory-card shadow-sm {{ $activeUserTab === 'teachers' ? '' : 'd-none' }}" data-table-tab-panel="teachers" role="tabpanel" @if ($activeUserTab !== 'teachers') hidden @endif>
            <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                <h3 class="h4 mb-0">Teachers</h3>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 users-table compact-data-table mobile-card-table">
                    <colgroup>
                        <col style="width: 24%;">
                        <col style="width: 23%;">
                        <col style="width: 24%;">
                        <col style="width: 18%;">
                        <col style="width: 11%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($teachers as $teacher)
                            <tr>
                                <td class="mobile-primary-cell" data-label="User">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar">{{ strtoupper(substr($teacher->displayName(), 0, 1)) }}</span>
                                        <div>
                                            <p class="fw-bold teacher-name">{{ $teacher->displayName() }}</p>
                                            <p class="small text-secondary mb-0">{{ $teacher->instructorProfile?->employee_number ?? 'Not assigned' }}</p>
                                            @if ($teacher->name !== $teacher->displayName())
                                                <p class="small text-secondary mb-0 teacher-subtext">{{ $teacher->name }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Email">{{ $teacher->email }}</td>
                                <td data-label="Department">
                                    @if ($teacher->instructorProfile?->department)
                                        <div>
                                            <p class="fw-semibold mb-0">{{ $teacher->instructorProfile->department->dept_name }}</p>
                                            <p class="small text-secondary mb-0">{{ $teacher->instructorProfile->department->college?->college_name }}</p>
                                        </div>
                                    @else
                                        <span class="text-secondary fst-italic">Not assigned</span>
                                    @endif
                                </td>
                                <td data-label="Designation">
                                    <div class="d-flex flex-wrap gap-2">
                                        @if ($teacher->hasRole('admin_dean'))
                                            <span class="badge text-bg-primary rounded-1">Dean</span>
                                        @endif
                                        @if ($teacher->hasRole('department_chair'))
                                            <span class="badge text-bg-info rounded-1">Department Chair</span>
                                        @endif
                                        @if (! $teacher->hasRole('admin_dean') && ! $teacher->hasRole('department_chair'))
                                            <span class="text-secondary fst-italic">Teacher only</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center" data-label="Actions">
                                    @include('partials.account-row-actions', ['accountUser' => $teacher])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center py-5 mobile-empty-cell" colspan="5">
                                    <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">badge</span></div>
                                    <h4 class="h4" style="color: var(--psu-navy);">No teachers found</h4>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
                <span class="small text-secondary">Showing {{ $teachers->count() }} {{ $teachers->count() === 1 ? 'entry' : 'entries' }}</span>
            </div>
        </section>

        <section class="directory-card shadow-sm {{ $activeUserTab === 'students' ? '' : 'd-none' }}" data-table-tab-panel="students" role="tabpanel" @if ($activeUserTab !== 'students') hidden @endif>
            <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                <h3 class="h4 mb-0">Students</h3>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 users-table compact-data-table mobile-card-table">
                    <colgroup>
                        <col style="width: 28%;">
                        <col style="width: 34%;">
                        <col style="width: 27%;">
                        <col style="width: 11%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $student)
                            <tr>
                                <td class="mobile-primary-cell" data-label="Student">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar">{{ strtoupper(substr($student->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                        <div style="min-width: 0;">
                                            <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $student->user?->displayName() ?? 'Unnamed student' }}</p>
                                            <p class="small text-secondary mb-0">{{ $student->student_number ?? 'Not assigned' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Program">
                                    @if ($student->program)
                                        <p class="fw-semibold mb-0">{{ $student->program->program_name }}</p>
                                        <p class="small text-secondary mb-0">
                                            {{ collect([$student->program->department?->dept_name, $student->program->department?->college?->college_name])->filter()->join(' - ') }}
                                        </p>
                                    @else
                                        <span class="text-secondary fst-italic">No program</span>
                                    @endif
                                </td>
                                <td data-label="Email">{{ $student->user?->email ?? 'No email' }}</td>
                                <td class="text-center" data-label="Actions">
                                    @if ($student->user)
                                        @include('partials.account-row-actions', ['accountUser' => $student->user])
                                    @else
                                        <span class="text-secondary fst-italic">No account</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center py-5 mobile-empty-cell" colspan="4">
                                    <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">groups</span></div>
                                    <h4 class="h4" style="color: var(--psu-navy);">No students found</h4>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
                <span class="small text-secondary">Showing {{ $students->count() }} {{ $students->count() === 1 ? 'entry' : 'entries' }}</span>
            </div>
        </section>
    </div>

    @include('partials.account-action-popups', [
        'accountUsers' => $teachers,
        'accountRoutePrefix' => 'department-chair',
        'accountMode' => 'instructor',
        'accountFixedDepartment' => $scopedDepartment,
    ])
    @include('partials.account-action-popups', [
        'accountUsers' => $students->pluck('user')->filter(),
        'accountRoutePrefix' => 'department-chair',
        'accountMode' => 'student',
        'accountPrograms' => $scopedPrograms,
    ])
    @include('department-chair.teachers.create-instructor-form')
    @include('department-chair.students.create-student-form')
    @include('partials.student-account-import', [
        'studentImportPrograms' => $scopedPrograms,
        'studentImportSampleRoute' => route('department-chair.students.import.sample'),
        'studentImportPreviewRoute' => route('department-chair.students.import.preview'),
        'studentImportConfirmRoute' => route('department-chair.students.import.confirm'),
    ])
@endsection
