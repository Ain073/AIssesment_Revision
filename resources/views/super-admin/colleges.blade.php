<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Colleges &amp; Departments | AIssessment Super Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <style>
        :root {
            --psu-navy: #00113a;
            --psu-navy-2: #002366;
            --psu-gold: #ffe16d;
            --psu-bg: #f8f9ff;
            --psu-line: #c5c6d2;
            --psu-muted: #444650;
        }

        body {
            background: var(--psu-bg);
            color: #0d1c2f;
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
            background: var(--psu-navy);
            z-index: 1040;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.72);
            text-decoration: none;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: var(--psu-navy-2);
        }

        .sidebar-link.active {
            border-left: 4px solid var(--psu-gold);
            padding-left: calc(1.25rem - 4px);
        }

        .topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 280px;
            height: 64px;
            background: #fff;
            border-bottom: 1px solid var(--psu-line);
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
        }

        .directory-header {
            background: var(--psu-navy);
            color: #fff;
        }

        .table thead th {
            background: #eff4ff;
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

        .icon-box {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--psu-navy-2);
            color: #b3c5ff;
            border-radius: 0.25rem;
        }

        .btn-psu {
            background: var(--psu-navy);
            border-color: var(--psu-navy);
            color: #fff;
            font-weight: 700;
        }

        .btn-psu:hover,
        .btn-psu:focus {
            background: var(--psu-navy-2);
            border-color: var(--psu-navy-2);
            color: #fff;
        }

        .btn-outline-psu {
            border-color: var(--psu-navy);
            color: var(--psu-navy);
            font-weight: 700;
        }

        .btn-outline-psu:hover {
            background: #eff4ff;
            border-color: var(--psu-navy);
            color: var(--psu-navy);
        }

        .nav-tabs .nav-link {
            color: var(--psu-muted);
            font-weight: 700;
            border: 0;
            border-bottom: 2px solid transparent;
            border-radius: 0;
        }

        .nav-tabs .nav-link.active {
            color: var(--psu-navy);
            background: transparent;
            border-bottom-color: var(--psu-navy);
        }

        .modal-header {
            background: var(--psu-navy);
            color: #fff;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #eff4ff;
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
            border: 1px solid #d7dceb;
            background: #f8faff;
            color: var(--psu-navy);
            text-decoration: none;
            font-weight: 600;
            border-radius: 0.4rem;
        }

        .profile-menu-link:hover,
        .profile-menu-link:focus {
            background: #eef4ff;
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
                <img src="{{ asset('images/psu-logo-transparent.png') }}" alt="PSU Seal" style="width: 48px; height: 48px; object-fit: contain;">
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
            <a class="sidebar-link active" href="{{ route('super-admin.colleges') }}">
                <span class="material-symbols-outlined">account_balance</span>
                <span class="sidebar-text">Colleges &amp; Departments</span>
            </a>
            <a class="sidebar-link" href="{{ route('super-admin.users') }}">
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
        <div class="d-flex align-items-center gap-4">
            <h2 class="h2 fw-semibold mb-0" style="color: var(--psu-navy);">Colleges &amp; Departments</h2>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="input-group d-none d-lg-flex" style="width: 320px;">
                <input class="form-control" placeholder="Search records..." type="text">
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
                <div class="col-md-6">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Total Colleges</p>
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalColleges }}</span>
                            <span class="small text-secondary">{{ $totalColleges === 1 ? '1 record' : 'Records' }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Total Departments</p>
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalDepartments }}</span>
                            <span class="small text-secondary">Across all units</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
                <button class="btn btn-outline-psu d-flex align-items-center gap-2" data-bs-target="#departmentModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">add_business</span>
                    Add Department
                </button>
                <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#collegeModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">add</span>
                    Add College
                </button>
            </div>

            <ul class="nav nav-tabs mb-3" id="directoryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-3" data-bs-target="#collegesPane" data-bs-toggle="tab" type="button" role="tab">Colleges</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-3" data-bs-target="#departmentsPane" data-bs-toggle="tab" type="button" role="tab">Departments</button>
                </li>
            </ul>

            <div class="tab-content">
                <section class="tab-pane fade show active directory-card shadow-sm" id="collegesPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Colleges List</h3>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white" type="button"><span class="material-symbols-outlined">filter_list</span></button>
                            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white" type="button"><span class="material-symbols-outlined">download</span></button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>College Name</th>
                                    <th>Departments</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($colleges as $college)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="icon-box"><span class="material-symbols-outlined">account_balance</span></span>
                                                <span class="fw-bold" style="color: var(--psu-navy);">{{ $college->college_name }}</span>
                                            </div>
                                        </td>
                                        <td class="fw-semibold">
                                            {{ $college->departments_count }} {{ $college->departments_count === 1 ? 'Department' : 'Departments' }}
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"><span class="material-symbols-outlined fs-6">visibility</span></button>
                                            <button class="btn btn-sm btn-outline-secondary" type="button"><span class="material-symbols-outlined fs-6">edit</span></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="3">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">account_balance</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No colleges yet</h4>
                                            <p class="text-secondary mb-4">Create your first college to start setting up academic units and departments.</p>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#collegeModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">add</span>
                                                Add First College
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
                        <span class="small text-secondary">Showing {{ $colleges->count() }} {{ $colleges->count() === 1 ? 'entry' : 'entries' }}</span>
                        <span class="badge text-bg-primary rounded-1">1</span>
                    </div>
                </section>

                <section class="tab-pane fade directory-card shadow-sm" id="departmentsPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Departments List</h3>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white" type="button"><span class="material-symbols-outlined">filter_list</span></button>
                            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white" type="button"><span class="material-symbols-outlined">download</span></button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th>College</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($departments as $department)
                                    <tr>
                                        <td class="fw-bold" style="color: var(--psu-navy);">{{ $department->dept_name }}</td>
                                        <td>{{ $department->college?->college_name }}</td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"><span class="material-symbols-outlined fs-6">visibility</span></button>
                                            <button class="btn btn-sm btn-outline-secondary" type="button"><span class="material-symbols-outlined fs-6">edit</span></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="3">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">add_business</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No departments yet</h4>
                                            <p class="text-secondary mb-4">Add a department after creating at least one college.</p>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#departmentModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">add</span>
                                                Add First Department
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
                        <span class="small text-secondary">Showing {{ $departments->count() }} {{ $departments->count() === 1 ? 'entry' : 'entries' }}</span>
                        <span class="badge text-bg-primary rounded-1">1</span>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <div class="modal fade" id="collegeModal" tabindex="-1" aria-labelledby="collegeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.colleges.store') }}" class="modal-content" method="POST">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title h4" id="collegeModalLabel">New College</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="college_name">College Name</label>
                    <input class="form-control form-control-lg" id="college_name" name="college_name" placeholder="e.g. College of Information Technology" required type="text" value="{{ old('college_name') }}">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit">Save College</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="departmentModal" tabindex="-1" aria-labelledby="departmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.departments.store') }}" class="modal-content" method="POST">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title h4" id="departmentModalLabel">New Department</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="college_id">College</label>
                        <select class="form-select form-select-lg" id="college_id" name="college_id" required @disabled($colleges->isEmpty())>
                            @forelse ($colleges as $college)
                                <option value="{{ $college->college_id }}" @selected(old('college_id') == $college->college_id)>{{ $college->college_name }}</option>
                            @empty
                                <option>No colleges available yet</option>
                            @endforelse
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold text-uppercase small" for="dept_name">Department Name</label>
                        <input class="form-control form-control-lg" id="dept_name" name="dept_name" placeholder="e.g. Department of Information Technology" required type="text" value="{{ old('dept_name') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($colleges->isEmpty())>Save Department</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
