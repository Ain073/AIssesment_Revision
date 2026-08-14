@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $showTopbarSearch = true;
    $topbarSearchPlaceholder = 'Search accounts...';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => request()->routeIs('super-admin.dashboard')],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => request()->routeIs('super-admin.colleges')],
        ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active' => request()->routeIs('super-admin.programs')],
        ['label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('super-admin.subjects'), 'active' => request()->routeIs('super-admin.subjects')],
        ['label' => 'Deans & Department Chairs', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active' => request()->routeIs('super-admin.roles')],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => request()->routeIs('super-admin.users')],
    ];
@endphp

@section('title', 'Users | AIssessment Super Admin')
@section('header', 'Users')


@section('content')
            {{-- Page messages --}}
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('mail_warning'))
                <div class="alert alert-warning">
                    {{ session('mail_warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Page actions --}}
            <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4 super-admin-toolbar">
                <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#createInstructorModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">person_add</span>
                    Create Instructor
                </button>
                <button class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-target="#createStudentModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">person_add</span>
                    Create Student
                </button>
            </div>

            <div class="table-switch-tabs" id="userTabs" role="tablist">
                <button class="btn btn-outline-primary table-switch-button active d-inline-flex align-items-center gap-2" data-bs-target="#teachersPane" data-bs-toggle="tab" type="button" role="tab">
                    <span class="material-symbols-outlined fs-5">badge</span>
                    Teachers
                    <span class="table-switch-count">{{ $teachers->count() }}</span>
                </button>
                <button class="btn btn-outline-primary table-switch-button d-inline-flex align-items-center gap-2" data-bs-target="#studentsPane" data-bs-toggle="tab" type="button" role="tab">
                    <span class="material-symbols-outlined fs-5">groups</span>
                    Students
                    <span class="table-switch-count">{{ $students->count() }}</span>
                </button>
            </div>

            <div class="tab-content">
                {{-- Teachers table --}}
                <section class="tab-pane fade show active directory-card shadow-sm" id="teachersPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Teachers</h3>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 users-table teachers-table mobile-card-table">
                            <colgroup>
                                <col style="width: 25%;">
                                <col style="width: 19%;">
                                <col style="width: 23%;">
                                <col style="width: 14%;">
                                <col style="width: 8%;">
                                <col style="width: 11%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th class="text-center">Elevated Access</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($teachers as $user)
                                    <tr>
                                        <td class="mobile-primary-cell" data-label="User">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div class="user-summary">
                                                    <p class="fw-bold user-name">{{ $user->displayName() }}</p>
                                                    <p class="small text-secondary mb-0">{{ $user->instructorProfile?->employee_number ?? 'Not assigned' }}</p>
                                                    @if ($user->name !== $user->displayName())
                                                        <p class="small text-secondary mb-0 user-subtext">{{ $user->name }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Email">{{ $user->email }}</td>
                                        <td data-label="Department">
                                            @if ($user->instructorProfile?->department)
                                                <div>
                                                    <p class="fw-semibold mb-0">{{ $user->instructorProfile->department->dept_name }}</p>
                                                    <p class="small text-secondary mb-0">{{ $user->instructorProfile->department->college?->college_name }}</p>
                                                </div>
                                            @else
                                                <span class="text-secondary fst-italic">Not assigned</span>
                                            @endif
                                        </td>
                                        <td class="text-center" data-label="Elevated Access">
                                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                                @if ($user->hasRole('admin_dean'))
                                                    <span class="badge text-bg-primary rounded-1">Admin/Dean</span>
                                                @endif
                                                @if ($user->hasRole('department_chair'))
                                                    <span class="badge text-bg-info rounded-1">Department Chair</span>
                                                @endif
                                                @if (! $user->hasRole('admin_dean') && ! $user->hasRole('department_chair'))
                                                    <span class="text-secondary fst-italic">No elevated access</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-center" data-label="Status">
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center" data-label="Actions">
                                            <button class="btn btn-sm record-action-trigger" data-bs-target="#viewUserModal{{ $user->id }}" data-bs-toggle="modal" type="button" aria-label="Manage {{ $user->displayName() }}">
                                                <span class="material-symbols-outlined fs-6">visibility</span>
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5 mobile-empty-cell" colspan="6">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">school</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No teacher accounts yet</h4>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createInstructorModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">person_add</span>
                                                Create Instructor
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
                        <span class="small text-secondary">Showing {{ $teachers->count() }} {{ $teachers->count() === 1 ? 'entry' : 'entries' }}</span>
                    </div>
                </section>

                {{-- Students table --}}
                <section class="tab-pane fade directory-card shadow-sm" id="studentsPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Students</h3>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 users-table students-table mobile-card-table">
                            <colgroup>
                                <col style="width: 31%;">
                                <col style="width: 22%;">
                                <col style="width: 28%;">
                                <col style="width: 8%;">
                                <col style="width: 11%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Program</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($students as $user)
                                    <tr>
                                        <td class="mobile-primary-cell" data-label="User">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div class="user-summary">
                                                    <p class="fw-bold user-name">{{ $user->displayName() }}</p>
                                                    <p class="small text-secondary mb-0">{{ $user->studentProfile?->student_number ?? 'Not assigned' }}</p>
                                                    @if ($user->name !== $user->displayName())
                                                        <p class="small text-secondary mb-0 user-subtext">{{ $user->name }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Email">{{ $user->email }}</td>
                                        <td data-label="Program">
                                            @if ($user->studentProfile?->program)
                                                <div>
                                                    <p class="fw-semibold mb-0">{{ $user->studentProfile->program->program_name }}</p>
                                                    <p class="small text-secondary mb-0">{{ $user->studentProfile->program->college?->college_name }}</p>
                                                </div>
                                            @else
                                                <span class="text-secondary fst-italic">Not assigned</span>
                                            @endif
                                        </td>
                                        <td data-label="Status">
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center" data-label="Actions">
                                            <button class="btn btn-sm record-action-trigger" data-bs-target="#viewUserModal{{ $user->id }}" data-bs-toggle="modal" type="button" aria-label="Manage {{ $user->displayName() }}">
                                                <span class="material-symbols-outlined fs-6">visibility</span>
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5 mobile-empty-cell" colspan="5">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">groups</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No student accounts yet</h4>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createStudentModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">person_add</span>
                                                Create Student
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
                        <span class="small text-secondary">Showing {{ $students->count() }} {{ $students->count() === 1 ? 'entry' : 'entries' }}</span>
                    </div>
                </section>

            </div>

    @include('super-admin.users.view-edit-delete-user-popups')
    @include('super-admin.users.create-instructor-form')
    @include('super-admin.users.create-student-form')
    @include('partials.student-account-import', [
        'studentImportPrograms' => $programs,
        'studentImportSampleRoute' => route('super-admin.users.students.import.sample'),
        'studentImportPreviewRoute' => route('super-admin.users.students.import.preview'),
        'studentImportConfirmRoute' => route('super-admin.users.students.import.confirm'),
    ])
@endsection

@push('scripts')
    @include('super-admin.users.user-page-code')
@endpush
