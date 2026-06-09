<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users | AIssessment Super Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <style>
        :root {
            --psu-navy: #001a70;
            --psu-navy-2: #0927d8;
            --psu-gold: #ffda27;
            --psu-gold-soft: #fff5bf;
            --psu-bg: #f7f9ff;
            --psu-line: #d6ddf5;
            --psu-muted: #5f6780;
            --psu-text: #1a1f2c;
        }

        body {
            background: var(--psu-bg);
            color: var(--psu-text);
            font-family: "Inter", sans-serif;
            overflow-x: hidden;
        }

        h1, h2, h3, .brand-text {
            font-family: "Oswald", sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 280px;
            background:
                radial-gradient(circle at 100% 0%, rgba(255, 218, 39, 0.28) 0%, rgba(255, 218, 39, 0) 28%),
                linear-gradient(180deg, #00124f 0%, var(--psu-navy) 46%, var(--psu-navy-2) 82%, #2346ff 100%);
            box-shadow: 12px 0 28px rgba(0, 26, 112, 0.16);
            z-index: 1040;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-weight: 700;
            letter-spacing: 0.02em;
            transition: background-color 0.18s ease, color 0.18s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
        }

        .sidebar-link.active {
            border-left: 4px solid var(--psu-gold);
            padding-left: calc(1.25rem - 4px);
            box-shadow: inset 0 -1px 0 rgba(255, 218, 39, 0.12), inset 0 1px 0 rgba(255, 218, 39, 0.12);
        }

        .topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 280px;
            height: 64px;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid var(--psu-line);
            box-shadow: 0 8px 24px rgba(9, 39, 216, 0.05);
            z-index: 1030;
        }

        .main-content {
            margin-left: 280px;
            padding-top: 64px;
            min-height: 100vh;
        }

        .page-container {
            max-width: 1440px;
            padding: 1.5rem;
        }

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
        }

        .btn-psu {
            background: var(--psu-navy-2);
            border-color: var(--psu-navy-2);
            color: #fff;
            font-weight: 700;
            box-shadow: 0 10px 18px rgba(9, 39, 216, 0.16);
        }

        .btn-psu:hover,
        .btn-psu:focus {
            background: var(--psu-navy);
            border-color: var(--psu-navy);
            color: #fff;
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
            justify-content: flex-end;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .users-table {
            min-width: 1180px;
        }

        .user-summary {
            min-width: 0;
        }

        .user-name {
            margin-bottom: 0;
            color: var(--psu-navy);
            white-space: nowrap;
        }

        .user-subtext {
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

        .profile-menu-shell {
            position: relative;
            margin-top: auto;
            padding: 0.75rem;
        }

        .profile-menu-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            color: inherit;
            padding: 0;
            text-align: left;
        }

        .profile-menu-toggle:hover,
        .profile-menu-toggle:focus {
            background: rgba(255, 255, 255, 0.06);
        }

        .profile-menu-panel {
            position: absolute;
            left: calc(100% + 12px);
            bottom: 12px;
            width: 220px;
            padding: 0.75rem;
            background: #fff;
            border: 1px solid rgba(0, 17, 58, 0.12);
            border-radius: 0.6rem;
            box-shadow: 0 18px 40px rgba(0, 17, 58, 0.2);
            z-index: 1080;
        }

        .profile-menu-link {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid #d8e0fb;
            background: #f5f8ff;
            color: var(--psu-navy);
            text-decoration: none;
            font-weight: 600;
            border-radius: 0.4rem;
        }

        .profile-menu-link:hover,
        .profile-menu-link:focus {
            background: #edf2ff;
            color: var(--psu-navy);
        }

        .profile-menu-link.disabled {
            opacity: 0.55;
            pointer-events: none;
        }

        .profile-menu-form {
            margin: 0;
        }

        .profile-logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid #f0c7cc;
            background: #fff5f6;
            color: #9f1d2a;
            font-weight: 700;
            border-radius: 0.4rem;
        }

        .profile-logout-btn:hover,
        .profile-logout-btn:focus {
            background: #ffe8eb;
            color: #9f1d2a;
        }

        .modal-header {
            background: var(--psu-navy);
            color: #fff;
        }

        .profile-section[hidden] {
            display: none !important;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                width: 86px;
            }

            .sidebar .sidebar-text,
            .sidebar .small,
            .sidebar .brand-text {
                display: none;
            }

            .topbar,
            .main-content {
                left: 86px;
                margin-left: 86px;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar d-flex flex-column">
        <div class="p-4">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ asset('images/psu-logo-transparent.png') }}" alt="PSU Seal" style="width: 64px; height: 64px; object-fit: contain;">
                <div class="sidebar-text">
                    <h1 class="brand-text h3 fw-bold text-white mb-1">AIssessment</h1>
                    <p class="small fw-bold text-white-50 mb-0">Super Admin Panel</p>
                </div>
            </div>
        </div>

        <nav class="flex-grow mt-4">
            <a class="sidebar-link" href="{{ route('super-admin.dashboard') }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a class="sidebar-link" href="{{ route('super-admin.colleges') }}">
                <span class="material-symbols-outlined">account_balance</span>
                <span class="sidebar-text">Colleges &amp; Departments</span>
            </a>
            <a class="sidebar-link active" href="{{ route('super-admin.users') }}">
                <span class="material-symbols-outlined">person_search</span>
                <span class="sidebar-text">Users</span>
            </a>
        </nav>

        <div class="border-top border-white border-opacity-10 profile-menu-shell">
            <button class="profile-menu-toggle" data-bs-target="#sidebarProfileMenu" data-bs-toggle="collapse" type="button" aria-expanded="false" aria-controls="sidebarProfileMenu">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px; background: var(--psu-gold); color: var(--psu-navy);">SA</div>
                    <div class="sidebar-text">
                        <p class="fw-bold text-white mb-0">Super Admin</p>
                        <p class="small text-white-50 text-uppercase mb-0">System Controller</p>
                    </div>
                </div>
            </button>
            <div class="collapse profile-menu-panel" id="sidebarProfileMenu">
                <div class="d-grid gap-2">
                    <a class="profile-menu-link disabled" href="#" aria-disabled="true">
                        <span class="material-symbols-outlined">settings</span>
                        <span class="sidebar-text">Settings</span>
                    </a>
                    <form action="{{ route('logout') }}" class="profile-menu-form" method="POST">
                        @csrf
                        <button class="profile-logout-btn" type="submit">
                            <span class="material-symbols-outlined">logout</span>
                            <span class="sidebar-text">Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <header class="topbar d-flex align-items-center justify-content-between px-4">
        <h2 class="brand-text h4 fw-semibold mb-0" style="color: var(--psu-navy);">Users</h2>
        <div class="d-flex align-items-center gap-3">
            <div class="input-group d-none d-lg-flex" style="width: 320px;">
                <input class="form-control" placeholder="Search accounts..." type="text">
                <span class="input-group-text bg-white"><span class="material-symbols-outlined fs-6">search</span></span>
            </div>
            <button class="btn btn-link text-secondary p-1" type="button"><span class="material-symbols-outlined">notifications</span></button>
            <button class="btn btn-link text-secondary p-1" type="button"><span class="material-symbols-outlined">help_outline</span></button>
        </div>
    </header>

    <main class="main-content">
        <div class="page-container mx-auto">
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Total Accounts</p>
                        <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalUsers }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Teacher Accounts</p>
                        <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalTeachers }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Student Accounts</p>
                        <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalStudents }}</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Authorized Teachers</p>
                        <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalAdminDeans + $totalDepartmentChairs }}</span>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
                <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#createUserModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">person_add</span>
                    Create User
                </button>
            </div>

            <ul class="nav nav-tabs mb-3" id="userTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-3" data-bs-target="#teachersPane" data-bs-toggle="tab" type="button" role="tab">Teachers</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-3" data-bs-target="#studentsPane" data-bs-toggle="tab" type="button" role="tab">Students</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-3" data-bs-target="#adminDeansPane" data-bs-toggle="tab" type="button" role="tab">Admin/Dean</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-3" data-bs-target="#departmentChairsPane" data-bs-toggle="tab" type="button" role="tab">Department Chairs</button>
                </li>
            </ul>

            <div class="tab-content">
                <section class="tab-pane fade show active directory-card shadow-sm" id="teachersPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Teachers</h3>
                        <span class="small text-white-50">Faculty accounts with authorization managed from edit</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Employee No.</th>
                                    <th>Department</th>
                                    <th>Elevated Access</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
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
                                                    @if ($user->name !== $user->displayName())
                                                        <p class="small text-secondary mb-0 user-subtext">{{ $user->name }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->instructorProfile?->employee_number ?? 'Not assigned' }}</td>
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
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
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
                                        <td>
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" data-bs-target="#viewUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                                    View
                                                </button>
                                                <button class="btn btn-sm btn-outline-psu d-inline-flex align-items-center gap-1" data-bs-target="#editUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">edit</span>
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" data-bs-target="#deleteUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">delete</span>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="7">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">school</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No teacher accounts yet</h4>
                                            <p class="text-secondary mb-4">Create the first teacher account to start assigning academic roles.</p>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createUserModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">person_add</span>
                                                Create Teacher
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

                <section class="tab-pane fade directory-card shadow-sm" id="studentsPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Students</h3>
                        <span class="small text-white-50">Student accounts listed separately for cleaner management</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Student No.</th>
                                    <th>Program</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($students as $user)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div class="user-summary">
                                                    <p class="fw-bold user-name">{{ $user->displayName() }}</p>
                                                    @if ($user->name !== $user->displayName())
                                                        <p class="small text-secondary mb-0 user-subtext">{{ $user->name }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->studentProfile?->student_number ?? 'Not assigned' }}</td>
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
                                        <td class="text-end">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" data-bs-target="#viewUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                                    View
                                                </button>
                                                <button class="btn btn-sm btn-outline-psu d-inline-flex align-items-center gap-1" data-bs-target="#editUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">edit</span>
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" data-bs-target="#deleteUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">delete</span>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="6">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">groups</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No student accounts yet</h4>
                                            <p class="text-secondary mb-4">Create the first student account to populate the student list.</p>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createUserModal" data-bs-toggle="modal" type="button">
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
                        <span class="small text-secondary">Total accounts: {{ $users->count() }}</span>
                    </div>
                </section>

                <section class="tab-pane fade directory-card shadow-sm" id="adminDeansPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Admin/Dean</h3>
                        <span class="small text-white-50">Teacher accounts with dean-level authorization</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Employee No.</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($adminDeans as $user)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div class="user-summary">
                                                    <p class="fw-bold user-name">{{ $user->displayName() }}</p>
                                                    <p class="small text-secondary mb-0 user-subtext">Teacher Account</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->instructorProfile?->employee_number ?? 'Not assigned' }}</td>
                                        <td>
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" data-bs-target="#viewUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                                    View
                                                </button>
                                                <button class="btn btn-sm btn-outline-psu d-inline-flex align-items-center gap-1" data-bs-target="#editUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">edit</span>
                                                    Edit
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="5">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">supervisor_account</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No Admin/Dean accounts yet</h4>
                                            <p class="text-secondary mb-0">Assign authorization from `Teachers > Edit` to make them appear here.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
                        <span class="small text-secondary">Showing {{ $adminDeans->count() }} {{ $adminDeans->count() === 1 ? 'entry' : 'entries' }}</span>
                        <span class="small text-secondary">Managed from teacher edit</span>
                    </div>
                </section>

                <section class="tab-pane fade directory-card shadow-sm" id="departmentChairsPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Department Chairs</h3>
                        <span class="small text-white-50">Teacher accounts with chair-level authorization</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Employee No.</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($departmentChairs as $user)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div class="user-summary">
                                                    <p class="fw-bold user-name">{{ $user->displayName() }}</p>
                                                    <p class="small text-secondary mb-0 user-subtext">Teacher Account</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->instructorProfile?->employee_number ?? 'Not assigned' }}</td>
                                        <td>{{ $user->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</td>
                                        <td>
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" data-bs-target="#viewUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                                    View
                                                </button>
                                                <button class="btn btn-sm btn-outline-psu d-inline-flex align-items-center gap-1" data-bs-target="#editUserModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                    <span class="material-symbols-outlined fs-6">edit</span>
                                                    Edit
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="6">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">groups</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No Department Chair accounts yet</h4>
                                            <p class="text-secondary mb-0">Assign authorization from `Teachers > Edit` to make them appear here.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
                        <span class="small text-secondary">Showing {{ $departmentChairs->count() }} {{ $departmentChairs->count() === 1 ? 'entry' : 'entries' }}</span>
                        <span class="small text-secondary">Managed from teacher edit</span>
                    </div>
                </section>
            </div>
        </div>
    </main>

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
                                    <input class="form-control" id="edit_first_name_{{ $user->id }}" name="first_name" required type="text" value="{{ old('form_mode') === 'edit' && (int) old('user_id') === $user->id ? old('first_name') : $user->first_name }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-uppercase small" for="edit_middle_name_{{ $user->id }}">Middle Name</label>
                                    <input class="form-control" id="edit_middle_name_{{ $user->id }}" name="middle_name" type="text" value="{{ old('form_mode') === 'edit' && (int) old('user_id') === $user->id ? old('middle_name') : $user->middle_name }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-uppercase small" for="edit_last_name_{{ $user->id }}">Last Name</label>
                                    <input class="form-control" id="edit_last_name_{{ $user->id }}" name="last_name" required type="text" value="{{ old('form_mode') === 'edit' && (int) old('user_id') === $user->id ? old('last_name') : $user->last_name }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-uppercase small" for="edit_email_{{ $user->id }}">Email</label>
                                    <input class="form-control" id="edit_email_{{ $user->id }}" name="email" required type="email" value="{{ old('form_mode') === 'edit' && (int) old('user_id') === $user->id ? old('email') : $user->email }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-uppercase small" for="edit_base_role_{{ $user->id }}">Account Type</label>
                                    @php
                                        $userBaseRole = $user->hasRole('instructor') ? 'instructor' : 'student';
                                        $editBaseRole = old('form_mode') === 'edit' && (int) old('user_id') === $user->id
                                            ? old('base_role')
                                            : $userBaseRole;
                                    @endphp
                                    <select class="form-select" id="edit_base_role_{{ $user->id }}" name="base_role" required>
                                        <option value="instructor" @selected($editBaseRole === 'instructor')>Teacher</option>
                                        <option value="student" @selected($editBaseRole === 'student')>Student</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-uppercase small" for="edit_status_{{ $user->id }}">Status</label>
                                    @php
                                        $editStatus = old('form_mode') === 'edit' && (int) old('user_id') === $user->id
                                            ? old('status')
                                            : $user->status;
                                    @endphp
                                    <select class="form-select" id="edit_status_{{ $user->id }}" name="status" required>
                                        <option value="active" @selected($editStatus === 'active')>Active</option>
                                        <option value="inactive" @selected($editStatus === 'inactive')>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-6 profile-section teacher-profile-section" id="edit_teacher_department_section_{{ $user->id }}">
                                    <label class="form-label fw-bold text-uppercase small" for="edit_department_id_{{ $user->id }}">Department</label>
                                    @php
                                        $editDepartmentId = old('form_mode') === 'edit' && (int) old('user_id') === $user->id
                                            ? old('department_id')
                                            : $user->instructorProfile?->department_id;
                                    @endphp
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
                                    <input class="form-control teacher-profile-field" id="edit_employee_number_{{ $user->id }}" name="employee_number" type="text" value="{{ old('form_mode') === 'edit' && (int) old('user_id') === $user->id ? old('employee_number') : $user->instructorProfile?->employee_number }}">
                                    <div class="form-text">Required for teacher accounts.</div>
                                </div>
                                <div class="col-md-6 profile-section student-profile-section" id="edit_student_program_section_{{ $user->id }}">
                                    <label class="form-label fw-bold text-uppercase small" for="edit_program_id_{{ $user->id }}">Program</label>
                                    @php
                                        $editProgramId = old('form_mode') === 'edit' && (int) old('user_id') === $user->id
                                            ? old('program_id')
                                            : $user->studentProfile?->program_id;
                                    @endphp
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
                                    <input class="form-control student-profile-field" id="edit_student_number_{{ $user->id }}" name="student_number" type="text" value="{{ old('form_mode') === 'edit' && (int) old('user_id') === $user->id ? old('student_number') : $user->studentProfile?->student_number }}">
                                    <div class="form-text">Required for student accounts.</div>
                                </div>
                                @php
                                    $isEditTarget = old('form_mode') === 'edit' && (int) old('user_id') === $user->id;
                                    $selectedAuthorizations = collect($isEditTarget ? old('authorizations', []) : [
                                        $user->hasRole('admin_dean') ? 'admin_dean' : null,
                                        $user->hasRole('department_chair') ? 'department_chair' : null,
                                    ])->filter()->values();
                                @endphp
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

    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form action="{{ route('super-admin.users.store') }}" class="modal-content" method="POST">
                @csrf
                <input name="form_mode" type="hidden" value="create">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="createUserModalLabel">Create User Account</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="first_name">First Name</label>
                            <input class="form-control" id="first_name" name="first_name" required type="text" value="{{ old('first_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="middle_name">Middle Name</label>
                            <input class="form-control" id="middle_name" name="middle_name" type="text" value="{{ old('middle_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="last_name">Last Name</label>
                            <input class="form-control" id="last_name" name="last_name" required type="text" value="{{ old('last_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="email">Email</label>
                            <input class="form-control" id="email" name="email" required type="email" value="{{ old('email') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-uppercase small" for="base_role">Account Type</label>
                            <select class="form-select" id="base_role" name="base_role" required>
                                <option value="instructor" @selected(old('base_role') === 'instructor')>Teacher</option>
                                <option value="student" @selected(old('base_role') === 'student')>Student</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-uppercase small" for="status">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6 profile-section teacher-profile-section" id="create_teacher_department_section">
                            <label class="form-label fw-bold text-uppercase small" for="department_id">Department</label>
                            <select class="form-select department-select" id="department_id" name="department_id">
                                <option value="">Select department</option>
                                @forelse ($departments as $department)
                                    <option value="{{ $department->department_id }}" @selected((string) old('department_id') === (string) $department->department_id)>
                                        {{ $department->dept_name }} - {{ $department->college?->college_name }}
                                    </option>
                                @empty
                                    <option value="">No departments available yet</option>
                                @endforelse
                            </select>
                            <div class="form-text">Required for teacher accounts.</div>
                        </div>
                        <div class="col-md-6 profile-section teacher-profile-section" id="create_teacher_employee_section">
                            <label class="form-label fw-bold text-uppercase small" for="employee_number">Employee Number</label>
                            <input class="form-control teacher-profile-field" id="employee_number" name="employee_number" type="text" value="{{ old('employee_number') }}">
                            <div class="form-text">Required for teacher accounts.</div>
                        </div>
                        <div class="col-md-6 profile-section student-profile-section" id="create_student_program_section">
                            <label class="form-label fw-bold text-uppercase small" for="program_id">Program</label>
                            <select class="form-select student-profile-field" id="program_id" name="program_id">
                                <option value="">Select program</option>
                                @forelse ($programs as $program)
                                    <option value="{{ $program->program_id }}" @selected((string) old('program_id') === (string) $program->program_id)>
                                        {{ $program->program_name }} - {{ $program->college?->college_name }}
                                    </option>
                                @empty
                                    <option value="">No programs available yet</option>
                                @endforelse
                            </select>
                            <div class="form-text">Required for student accounts.</div>
                        </div>
                        <div class="col-md-6 profile-section student-profile-section" id="create_student_number_section">
                            <label class="form-label fw-bold text-uppercase small" for="student_number">Student Number</label>
                            <input class="form-control student-profile-field" id="student_number" name="student_number" type="text" value="{{ old('student_number') }}">
                            <div class="form-text">Required for student accounts.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="password">Password</label>
                            <input class="form-control" id="password" name="password" required type="password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="password_confirmation">Confirm Password</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation" required type="password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function syncAuthorizationInputs(selectId) {
            const roleSelect = document.getElementById(selectId);

            if (! roleSelect) {
                return;
            }

            const checkboxes = document.querySelectorAll(`.authorization-checkbox[data-teacher-target="${selectId}"]`);
            const isTeacher = roleSelect.value === 'instructor';

            checkboxes.forEach((checkbox) => {
                checkbox.disabled = ! isTeacher;

                if (! isTeacher) {
                    checkbox.checked = false;
                }
            });
        }

        function syncDepartmentInput(selectId, departmentSelectId) {
            const roleSelect = document.getElementById(selectId);
            const departmentSelect = document.getElementById(departmentSelectId);

            if (! roleSelect || ! departmentSelect) {
                return;
            }

            const isTeacher = roleSelect.value === 'instructor';
            departmentSelect.disabled = ! isTeacher;
            departmentSelect.required = isTeacher;

            if (! isTeacher) {
                departmentSelect.value = '';
            }
        }

        function syncProfileFields(selectId) {
            const roleSelect = document.getElementById(selectId);

            if (! roleSelect) {
                return;
            }

            const isCreate = selectId === 'base_role';
            const suffix = isCreate ? '' : selectId.replace('edit_base_role_', '');
            const employeeInput = document.getElementById(isCreate ? 'employee_number' : `edit_employee_number_${suffix}`);
            const programSelect = document.getElementById(isCreate ? 'program_id' : `edit_program_id_${suffix}`);
            const studentNumberInput = document.getElementById(isCreate ? 'student_number' : `edit_student_number_${suffix}`);
            const teacherDepartmentSection = document.getElementById(isCreate ? 'create_teacher_department_section' : `edit_teacher_department_section_${suffix}`);
            const teacherEmployeeSection = document.getElementById(isCreate ? 'create_teacher_employee_section' : `edit_teacher_employee_section_${suffix}`);
            const studentProgramSection = document.getElementById(isCreate ? 'create_student_program_section' : `edit_student_program_section_${suffix}`);
            const studentNumberSection = document.getElementById(isCreate ? 'create_student_number_section' : `edit_student_number_section_${suffix}`);
            const authorizationSection = isCreate
                ? null
                : document.getElementById(`edit_authorization_section_${suffix}`);
            const isTeacher = roleSelect.value === 'instructor';
            const isStudent = roleSelect.value === 'student';

            if (teacherDepartmentSection) {
                teacherDepartmentSection.hidden = ! isTeacher;
            }

            if (teacherEmployeeSection) {
                teacherEmployeeSection.hidden = ! isTeacher;
            }

            if (studentProgramSection) {
                studentProgramSection.hidden = ! isStudent;
            }

            if (studentNumberSection) {
                studentNumberSection.hidden = ! isStudent;
            }

            if (authorizationSection) {
                authorizationSection.hidden = ! isTeacher;
            }

            if (employeeInput) {
                employeeInput.disabled = ! isTeacher;
                employeeInput.required = isTeacher;

                if (! isTeacher) {
                    employeeInput.value = '';
                }
            }

            if (programSelect) {
                programSelect.disabled = ! isStudent;
                programSelect.required = isStudent;

                if (! isStudent) {
                    programSelect.value = '';
                }
            }

            if (studentNumberInput) {
                studentNumberInput.disabled = ! isStudent;
                studentNumberInput.required = isStudent;

                if (! isStudent) {
                    studentNumberInput.value = '';
                }
            }
        }

        document.querySelectorAll('select[id^="edit_base_role_"]').forEach((select) => {
            syncAuthorizationInputs(select.id);
            select.addEventListener('change', () => syncAuthorizationInputs(select.id));
            syncDepartmentInput(select.id, select.id.replace('edit_base_role_', 'edit_department_id_'));
            select.addEventListener('change', () => syncDepartmentInput(select.id, select.id.replace('edit_base_role_', 'edit_department_id_')));
            syncProfileFields(select.id);
            select.addEventListener('change', () => syncProfileFields(select.id));
        });

        syncDepartmentInput('base_role', 'department_id');
        syncProfileFields('base_role');
        const createRoleSelect = document.getElementById('base_role');

        if (createRoleSelect) {
            createRoleSelect.addEventListener('change', () => syncDepartmentInput('base_role', 'department_id'));
            createRoleSelect.addEventListener('change', () => syncProfileFields('base_role'));
        }
    </script>
    @if ($errors->any())
        <script>
            const formMode = @json(old('form_mode', 'create'));
            const userId = @json(old('user_id'));
            const modalId = formMode === 'edit' && userId ? `editUserModal${userId}` : 'createUserModal';
            const modalElement = document.getElementById(modalId);

            if (modalElement) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        </script>
    @endif
</body>
</html>
