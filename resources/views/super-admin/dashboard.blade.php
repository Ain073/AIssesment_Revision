<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | AIssessment Super Admin</title>

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

        .dashboard-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }

        .icon-tile {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.35rem;
            background: #eff4ff;
            color: var(--psu-navy);
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

        .check-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            background: var(--psu-line);
        }

        .check-dot.done {
            background: #198754;
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
            <a class="sidebar-link active" href="{{ route('super-admin.dashboard') }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a class="sidebar-link" href="{{ route('super-admin.colleges') }}">
                <span class="material-symbols-outlined">account_balance</span>
                <span class="sidebar-text">Colleges &amp; Departments</span>
            </a>
            <a class="sidebar-link" href="{{ route('super-admin.users') }}">
                <span class="material-symbols-outlined">person_search</span>
                <span class="sidebar-text">Users</span>
            </a>
            <a class="sidebar-link" href="#">
                <span class="material-symbols-outlined">settings</span>
                <span class="sidebar-text">Settings</span>
            </a>
        </nav>

        <div class="p-3 border-top border-white border-opacity-10">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px; background: var(--psu-gold); color: var(--psu-navy);">SA</div>
                <div class="sidebar-text">
                    <p class="fw-bold text-white mb-0">Super Admin</p>
                    <p class="small text-white-50 text-uppercase mb-0">System Controller</p>
                </div>
            </div>
        </div>
    </aside>

    <header class="topbar d-flex align-items-center justify-content-between px-4">
        <h2 class="brand-text h4 fw-semibold mb-0" style="color: var(--psu-navy);">Super Admin Dashboard</h2>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link text-secondary p-1" type="button"><span class="material-symbols-outlined">notifications</span></button>
            <button class="btn btn-link text-secondary p-1" type="button"><span class="material-symbols-outlined">help_outline</span></button>
        </div>
    </header>

    <main class="main-content">
        <div class="page-container mx-auto">
            <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-end gap-3 mb-4">
                <a class="btn btn-psu d-inline-flex align-items-center gap-2" href="{{ route('super-admin.colleges') }}">
                    <span class="material-symbols-outlined fs-5">account_balance</span>
                    Manage Colleges &amp; Departments
                </a>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="dashboard-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Total Colleges</p>
                                <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalColleges }}</span>
                            </div>
                            <span class="icon-tile"><span class="material-symbols-outlined">account_balance</span></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="dashboard-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Total Departments</p>
                                <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalDepartments }}</span>
                            </div>
                            <span class="icon-tile"><span class="material-symbols-outlined">add_business</span></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="dashboard-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Admin/Dean Accounts</p>
                                <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalAdminDeans }}</span>
                            </div>
                            <span class="icon-tile"><span class="material-symbols-outlined">supervisor_account</span></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="dashboard-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="small fw-bold text-secondary text-uppercase mb-2">Department Chairs</p>
                                <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalDepartmentChairs }}</span>
                            </div>
                            <span class="icon-tile"><span class="material-symbols-outlined">groups</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <section class="dashboard-card p-4 h-100">
                        <h3 class="h4 mb-3" style="color: var(--psu-navy);">Setup Checklist</h3>
                        <div class="d-grid gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <span class="check-dot {{ $totalColleges > 0 ? 'done' : '' }}"></span>
                                <span>Create colleges</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="check-dot {{ $totalDepartments > 0 ? 'done' : '' }}"></span>
                                <span>Add departments</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="check-dot {{ $totalAdminDeans > 0 ? 'done' : '' }}"></span>
                                <span>Authorize Admin/Dean access</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="check-dot {{ $totalDepartmentChairs > 0 ? 'done' : '' }}"></span>
                                <span>Authorize Department Chair access</span>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-3">
                    <section class="dashboard-card p-4 h-100">
                        <h3 class="h4 mb-3" style="color: var(--psu-navy);">Quick Actions</h3>
                        <div class="d-grid gap-2">
                            <a class="btn btn-psu" href="{{ route('super-admin.colleges') }}">Add College</a>
                            <a class="btn btn-outline-secondary" href="{{ route('super-admin.colleges') }}">Add Department</a>
                            <a class="btn btn-outline-secondary" href="{{ route('super-admin.users') }}">Manage Users</a>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="dashboard-card p-4 h-100">
                        <h3 class="h4 mb-3" style="color: var(--psu-navy);">Recent Activity</h3>
                        <div class="text-center py-4">
                            <div class="icon-tile mx-auto mb-3"><span class="material-symbols-outlined">history</span></div>
                            <p class="fw-semibold mb-1">No recent activity yet</p>
                            <p class="small text-secondary mb-0">System actions will appear here once activity tracking is added.</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
