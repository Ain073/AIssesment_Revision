<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorization | AIssessment Super Admin</title>

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

        .avatar {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--psu-navy-2);
            color: var(--psu-gold);
            border-radius: 50%;
            font-weight: 800;
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

        .btn-outline-psu:hover,
        .btn-outline-psu:focus {
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
            <a class="sidebar-link" href="{{ route('super-admin.colleges') }}">
                <span class="material-symbols-outlined">account_balance</span>
                <span class="sidebar-text">Colleges &amp; Departments</span>
            </a>
            <a class="sidebar-link active" href="{{ route('super-admin.roles') }}">
                <span class="material-symbols-outlined">admin_panel_settings</span>
                <span class="sidebar-text">Authorization</span>
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
        <h2 class="brand-text h4 fw-semibold mb-0" style="color: var(--psu-navy);">Authorization</h2>
        <div class="d-flex align-items-center gap-3">
            <div class="input-group d-none d-lg-flex" style="width: 320px;">
                <input class="form-control" placeholder="Search teachers..." type="text">
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
                <div class="col-md-4">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Teacher Accounts</p>
                        <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalTeachers }}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Admin/Dean Authorized</p>
                        <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalAdminDeans }}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card p-4">
                        <p class="small fw-bold text-secondary text-uppercase mb-2">Department Chair Authorized</p>
                        <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalDepartmentChairs }}</span>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-3" id="authorizationTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-3" data-bs-target="#adminDeanPane" data-bs-toggle="tab" type="button" role="tab">Admin/Dean</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-3" data-bs-target="#departmentChairPane" data-bs-toggle="tab" type="button" role="tab">Department Chair</button>
                </li>
            </ul>

            <div class="tab-content">
                <section class="tab-pane fade show active directory-card shadow-sm" id="adminDeanPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Admin/Dean Authorization</h3>
                        <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white d-inline-flex align-items-center gap-1" data-bs-target="#grantAdminDeanModal" data-bs-toggle="modal" type="button">
                            <span class="material-symbols-outlined fs-6">person_add</span>
                            Add Authorization
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($adminDeans as $user)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div>
                                                    <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $user->displayName() }}</p>
                                                    <p class="small text-secondary mb-0">Teacher Account</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td>
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" data-bs-target="#removeAdminDeanModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-6">remove_circle</span>
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="4">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">supervisor_account</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No Admin/Dean authorization yet</h4>
                                            <p class="text-secondary mb-4">Assign a teacher account when you are ready to delegate dean-level access.</p>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#grantAdminDeanModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">person_add</span>
                                                Add Authorization
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
                        <span class="small text-secondary">Showing {{ $adminDeans->count() }} {{ $adminDeans->count() === 1 ? 'entry' : 'entries' }}</span>
                        <span class="small text-secondary">Available teachers: {{ $availableAdminDeanTeachers->count() }}</span>
                    </div>
                </section>

                <section class="tab-pane fade directory-card shadow-sm" id="departmentChairPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Department Chair Authorization</h3>
                        <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white d-inline-flex align-items-center gap-1" data-bs-target="#grantDepartmentChairModal" data-bs-toggle="modal" type="button">
                            <span class="material-symbols-outlined fs-6">person_add</span>
                            Add Authorization
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($departmentChairs as $user)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div>
                                                    <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $user->displayName() }}</p>
                                                    <p class="small text-secondary mb-0">Teacher Account</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td>
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" data-bs-target="#removeDepartmentChairModal{{ $user->id }}" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-6">remove_circle</span>
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="4">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">groups</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No Department Chair authorization yet</h4>
                                            <p class="text-secondary mb-4">Assign a teacher account when you are ready to delegate chair-level access.</p>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#grantDepartmentChairModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">person_add</span>
                                                Add Authorization
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
                        <span class="small text-secondary">Showing {{ $departmentChairs->count() }} {{ $departmentChairs->count() === 1 ? 'entry' : 'entries' }}</span>
                        <span class="small text-secondary">Available teachers: {{ $availableDepartmentChairTeachers->count() }}</span>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <div class="modal fade" id="grantAdminDeanModal" tabindex="-1" aria-labelledby="grantAdminDeanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.roles.grant') }}" class="modal-content" method="POST">
                @csrf
                <input name="form_mode" type="hidden" value="grant_admin_dean">
                <input name="role_name" type="hidden" value="admin_dean">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="grantAdminDeanModalLabel">Authorize Admin/Dean</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="admin_dean_user_id">Teacher Account</label>
                    <select class="form-select form-select-lg" id="admin_dean_user_id" name="user_id" required @disabled($availableAdminDeanTeachers->isEmpty())>
                        @forelse ($availableAdminDeanTeachers as $user)
                            <option value="{{ $user->id }}" @selected(old('form_mode') === 'grant_admin_dean' && (int) old('user_id') === $user->id)>{{ $user->displayName() }} - {{ $user->email }}</option>
                        @empty
                            <option>No available teacher accounts</option>
                        @endforelse
                    </select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($availableAdminDeanTeachers->isEmpty())>Confirm Authorization</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="grantDepartmentChairModal" tabindex="-1" aria-labelledby="grantDepartmentChairModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.roles.grant') }}" class="modal-content" method="POST">
                @csrf
                <input name="form_mode" type="hidden" value="grant_department_chair">
                <input name="role_name" type="hidden" value="department_chair">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="grantDepartmentChairModalLabel">Authorize Department Chair</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="department_chair_user_id">Teacher Account</label>
                    <select class="form-select form-select-lg" id="department_chair_user_id" name="user_id" required @disabled($availableDepartmentChairTeachers->isEmpty())>
                        @forelse ($availableDepartmentChairTeachers as $user)
                            <option value="{{ $user->id }}" @selected(old('form_mode') === 'grant_department_chair' && (int) old('user_id') === $user->id)>{{ $user->displayName() }} - {{ $user->email }}</option>
                        @empty
                            <option>No available teacher accounts</option>
                        @endforelse
                    </select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($availableDepartmentChairTeachers->isEmpty())>Confirm Authorization</button>
                </div>
            </form>
        </div>
    </div>

    @foreach ($adminDeans as $user)
        <div class="modal fade" id="removeAdminDeanModal{{ $user->id }}" tabindex="-1" aria-labelledby="removeAdminDeanModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('super-admin.roles.revoke') }}" class="modal-content" method="POST">
                    @csrf
                    @method('DELETE')
                    <input name="user_id" type="hidden" value="{{ $user->id }}">
                    <input name="role_name" type="hidden" value="admin_dean">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="removeAdminDeanModalLabel{{ $user->id }}">Remove Authorization</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Remove Admin/Dean authorization from this teacher?</p>
                        <div class="border rounded p-3" style="background: #eff4ff;">
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $user->displayName() }}</p>
                            <p class="small text-secondary mb-0">{{ $user->email }}</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger px-4" type="submit">Confirm Remove</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    @foreach ($departmentChairs as $user)
        <div class="modal fade" id="removeDepartmentChairModal{{ $user->id }}" tabindex="-1" aria-labelledby="removeDepartmentChairModalLabel{{ $user->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('super-admin.roles.revoke') }}" class="modal-content" method="POST">
                    @csrf
                    @method('DELETE')
                    <input name="user_id" type="hidden" value="{{ $user->id }}">
                    <input name="role_name" type="hidden" value="department_chair">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="removeDepartmentChairModalLabel{{ $user->id }}">Remove Authorization</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Remove Department Chair authorization from this teacher?</p>
                        <div class="border rounded p-3" style="background: #eff4ff;">
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $user->displayName() }}</p>
                            <p class="small text-secondary mb-0">{{ $user->email }}</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger px-4" type="submit">Confirm Remove</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @if ($errors->any())
        <script>
            const formMode = @json(old('form_mode'));
            const modalId = formMode === 'grant_department_chair'
                ? 'grantDepartmentChairModal'
                : formMode === 'grant_admin_dean'
                    ? 'grantAdminDeanModal'
                    : null;

            if (modalId) {
                const modalElement = document.getElementById(modalId);

                if (modalElement) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            }
        </script>
    @endif
</body>
</html>
