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
        }

        .btn-outline-psu {
            border-color: var(--psu-navy);
            color: var(--psu-navy);
            font-weight: 700;
        }

        .btn-outline-psu:hover {
            background: #edf2ff;
            border-color: var(--psu-navy);
            color: var(--psu-navy);
        }

        .nav-tabs .nav-link {
            color: var(--psu-muted);
            font-weight: 700;
            border: 0;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            white-space: nowrap;
        }

        .nav-tabs .nav-link.active {
            color: var(--psu-navy);
            background: transparent;
            border-bottom-color: var(--psu-gold);
        }

        #userTabs {
            flex-wrap: nowrap;
            gap: 1rem;
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
        }

        #userTabs::-webkit-scrollbar {
            height: 6px;
        }

        #userTabs::-webkit-scrollbar-thumb {
            background: rgba(9, 39, 216, 0.22);
            border-radius: 999px;
        }

        .action-buttons {
            display: flex;
            flex-wrap: nowrap;
            justify-content: center;
            gap: 0.35rem;
            white-space: nowrap;
        }

        .action-buttons .btn {
            width: 36px;
            height: 36px;
            padding: 0;
            flex: 0 0 36px;
            align-items: center;
            justify-content: center;
        }

        .users-table {
            width: 100%;
            min-width: 1180px;
            table-layout: fixed;
        }

        .users-table th,
        .users-table td {
            overflow-wrap: anywhere;
        }

        .user-summary {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            margin-bottom: 0;
            color: var(--psu-navy);
            line-height: 1.35;
            white-space: normal;
        }

        .user-subtext {
            white-space: normal;
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

        .modal-header {
            background: var(--psu-navy);
            color: #fff;
        }

        .profile-section[hidden] {
            display: none !important;
        }
    </style>
@endpush

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
            <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
                <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#createInstructorModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">person_add</span>
                    Create Instructor
                </button>
                <button class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-target="#createStudentModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">person_add</span>
                    Create Student
                </button>
            </div>

            <ul class="nav nav-tabs mb-3" id="userTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-3" data-bs-target="#teachersPane" data-bs-toggle="tab" type="button" role="tab">Teachers</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-3" data-bs-target="#studentsPane" data-bs-toggle="tab" type="button" role="tab">Students</button>
                </li>
            </ul>

            <div class="tab-content">
                {{-- Teachers table --}}
                <section class="tab-pane fade show active directory-card shadow-sm" id="teachersPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Teachers</h3>
                        <span class="small text-white-50">Faculty accounts with authorization managed from edit</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 users-table teachers-table">
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
                                        <td>
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
                                        <td>{{ $user->email }}</td>
                                        <td>
                                            @if ($user->instructorProfile?->department)
                                                <div>
                                                    <p class="fw-semibold mb-0">{{ $user->instructorProfile->department->dept_name }}</p>
                                                    <p class="small text-secondary mb-0">{{ $user->instructorProfile->department->college?->college_name }}</p>
                                                </div>
                                            @else
                                                <span class="text-secondary fst-italic">Not assigned</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
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
                                        <td class="text-center">
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-secondary d-inline-flex" data-bs-target="#viewUserModal{{ $user->id }}" data-bs-toggle="modal" type="button" title="View user" aria-label="View {{ $user->displayName() }}">
                                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                                </button>
                                                <button class="btn btn-sm btn-outline-psu d-inline-flex" data-bs-target="#editUserModal{{ $user->id }}" data-bs-toggle="modal" type="button" title="Edit user" aria-label="Edit {{ $user->displayName() }}">
                                                    <span class="material-symbols-outlined fs-6">edit</span>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger d-inline-flex" data-bs-target="#deleteUserModal{{ $user->id }}" data-bs-toggle="modal" type="button" title="Delete user" aria-label="Delete {{ $user->displayName() }}">
                                                    <span class="material-symbols-outlined fs-6">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="6">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">school</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No teacher accounts yet</h4>
                                            <p class="text-secondary mb-4">Create the first instructor account to start assigning academic roles.</p>
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
                        <span class="small text-secondary">Edit a teacher to manage authorization</span>
                    </div>
                </section>

                {{-- Students table --}}
                <section class="tab-pane fade directory-card shadow-sm" id="studentsPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Students</h3>
                        <span class="small text-white-50">Student accounts listed separately for cleaner management</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 users-table students-table">
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
                                        <td class="text-center">
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
                                        <td>{{ $user->email }}</td>
                                        <td>
                                            @if ($user->studentProfile?->program)
                                                <div>
                                                    <p class="fw-semibold mb-0">{{ $user->studentProfile->program->program_name }}</p>
                                                    <p class="small text-secondary mb-0">{{ $user->studentProfile->program->college?->college_name }}</p>
                                                </div>
                                            @else
                                                <span class="text-secondary fst-italic">Not assigned</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-secondary d-inline-flex" data-bs-target="#viewUserModal{{ $user->id }}" data-bs-toggle="modal" type="button" title="View user" aria-label="View {{ $user->displayName() }}">
                                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                                </button>
                                                <button class="btn btn-sm btn-outline-psu d-inline-flex" data-bs-target="#editUserModal{{ $user->id }}" data-bs-toggle="modal" type="button" title="Edit user" aria-label="Edit {{ $user->displayName() }}">
                                                    <span class="material-symbols-outlined fs-6">edit</span>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger d-inline-flex" data-bs-target="#deleteUserModal{{ $user->id }}" data-bs-toggle="modal" type="button" title="Delete user" aria-label="Delete {{ $user->displayName() }}">
                                                    <span class="material-symbols-outlined fs-6">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="5">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">groups</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No student accounts yet</h4>
                                            <p class="text-secondary mb-4">Create the first student account to populate the student list.</p>
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
                        <span class="small text-secondary">Student accounts only</span>
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
