@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => true],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => false],
        ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active' => false],
        ['label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('super-admin.subjects'), 'active' => false],
        ['label' => 'Deans & Department Chairs', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active' => false],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => false],
    ];
@endphp

@section('title', 'Dashboard | AIssessment Super Admin')
@section('header', 'Super Admin Dashboard')

@push('styles')
    <style>
        .hero-card {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(120deg, rgba(4, 25, 132, 0.98) 0%, rgba(8, 48, 210, 0.96) 58%, rgba(142, 137, 113, 0.96) 125%),
                linear-gradient(180deg, #03104f 0%, #0927d8 62%, #8f8a73 100%);
            color: #fff;
            border: 1px solid rgba(255, 218, 39, 0.34);
            border-radius: 0.5rem;
            box-shadow: 0 18px 34px rgba(0, 26, 112, 0.18);
            padding: 2rem 2.25rem;
        }

        .hero-card::after {
            position: absolute;
            inset: 0 0 0 auto;
            width: 38%;
            content: '';
            background: linear-gradient(135deg, rgba(255, 245, 191, 0.18), rgba(255, 255, 255, 0.06));
            transform: skewX(-12deg) translateX(22%);
        }

        .hero-card > * {
            position: relative;
            z-index: 1;
        }

        .hero-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, 260px);
            gap: 1.5rem;
            align-items: center;
        }

        .hero-eyebrow {
            color: rgba(255, 245, 191, 0.92);
            letter-spacing: 0.04em;
        }

        .hero-title {
            font-size: clamp(2.4rem, 3vw, 3.25rem);
            line-height: 1;
            margin-bottom: 0;
        }

        .hero-actions {
            display: grid;
            gap: 0.75rem;
        }

        .hero-action-link {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.8rem 0.95rem;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 0.45rem;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            text-decoration: none;
        }

        .hero-action-link:hover,
        .hero-action-link:focus {
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
        }

        .hero-action-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 40px;
            border-radius: 0.35rem;
            background: rgba(255, 245, 191, 0.2);
            color: var(--psu-gold);
        }

        .dashboard-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 10px 24px rgba(0, 26, 112, 0.05);
        }

        .stat-label {
            color: #667085;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .stat-value {
            color: var(--psu-navy);
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
            gap: 1rem;
        }

        .program-track {
            overflow: hidden;
            background: #e7ecf7;
            border-radius: 0.25rem;
        }

        .status-chart-wrap {
            display: grid;
            grid-template-columns: 180px minmax(0, 1fr);
            align-items: center;
            gap: 1.5rem;
        }

        .status-pie {
            width: 180px;
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: conic-gradient(
                #198754 0deg var(--active-angle),
                #dc3545 var(--active-angle) 360deg
            );
            box-shadow: inset 0 0 0 1px rgba(0, 26, 112, 0.08);
        }

        .status-pie::before {
            width: 58%;
            aspect-ratio: 1;
            content: "";
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 0 0 1px rgba(0, 26, 112, 0.08);
        }

        .status-pie-center {
            position: absolute;
            text-align: center;
        }

        .status-pie-shell {
            position: relative;
            display: inline-grid;
            place-items: center;
        }

        .status-legend {
            display: grid;
            gap: 1rem;
        }

        .status-legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            background: #f8faff;
            border: 1px solid #d9e2fb;
            border-radius: 0.45rem;
        }

        .setup-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .setup-item {
            padding: 1rem;
            background: #f8faff;
            border: 1px solid #d9e2fb;
            border-radius: 0.45rem;
        }

        .setup-value {
            color: var(--psu-navy);
            font-size: 1.35rem;
            font-weight: 800;
        }

        .program-row {
            display: grid;
            grid-template-columns: minmax(190px, 0.9fr) minmax(160px, 1.1fr) 72px;
            align-items: center;
            gap: 1rem;
            padding: 0.95rem 0;
            border-bottom: 1px solid #edf0f7;
        }

        .program-row:last-child {
            border-bottom: 0;
        }

        .program-track {
            height: 12px;
        }

        .program-fill {
            width: var(--bar-width);
            height: 100%;
            background: linear-gradient(90deg, var(--psu-navy), #198754);
            border-radius: inherit;
        }

        @media (max-width: 991.98px) {
            .hero-layout {
                grid-template-columns: 1fr;
            }

            .overview-grid,
            .program-row {
                grid-template-columns: 1fr;
            }

            .status-chart-wrap {
                grid-template-columns: 1fr;
                justify-items: center;
            }

            .setup-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .setup-grid {
                grid-template-columns: 1fr;
            }

            .status-pie {
                width: 150px;
            }
        }
    </style>
@endpush

@section('content')
    <section class="hero-card mb-4">
        <div class="hero-layout">
            <div>
                <p class="small fw-bold text-uppercase mb-2 hero-eyebrow">Welcome back</p>
                <h2 class="brand-text hero-title">Super Admin</h2>
            </div>

            <div class="hero-actions" aria-label="Quick actions">
                @foreach ($quickActions as $action)
                    <a class="hero-action-link" href="{{ $action['href'] }}">
                        <span class="hero-action-icon"><span class="material-symbols-outlined">{{ $action['icon'] }}</span></span>
                        <span>
                            <span class="fw-bold d-block">{{ $action['label'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Main account statistics --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="dashboard-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-2">Total Users</p>
                        <div class="stat-value">{{ $totalUsers }}</div>
                    </div>
                    <span class="icon-tile"><span class="material-symbols-outlined">groups</span></span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="dashboard-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-2">Active Accounts</p>
                        <div class="stat-value">{{ $activeUserPercentage }}%</div>
                    </div>
                    <span class="icon-tile"><span class="material-symbols-outlined">verified_user</span></span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="dashboard-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-2">Students</p>
                        <div class="stat-value">{{ $totalStudents }}</div>
                    </div>
                    <span class="icon-tile"><span class="material-symbols-outlined">school</span></span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="dashboard-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-2">Teachers</p>
                        <div class="stat-value">{{ $totalTeachers }}</div>
                    </div>
                    <span class="icon-tile"><span class="material-symbols-outlined">co_present</span></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Account status and academic setup --}}
    <div class="overview-grid mb-4">
        <section class="dashboard-card p-4">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                <div>
                    <p class="stat-label mb-2">Account Status</p>
                    <h2 class="h4 mb-0" style="color: var(--psu-navy);">Active vs Inactive</h2>
                </div>
                <span class="badge text-bg-light border rounded-1">{{ $totalUsers }} users</span>
            </div>

            <div class="status-chart-wrap">
                <div class="status-pie-shell">
                    <div
                        class="status-pie"
                        role="img"
                        aria-label="{{ $activeUserPercentage }} percent active and {{ $inactiveUserPercentage }} percent inactive"
                        style="--active-angle: {{ $totalUsers > 0 ? $activeUserPercentage * 3.6 : 0 }}deg;"
                    ></div>
                    <div class="status-pie-center">
                        <div class="setup-value">{{ $activeUserPercentage }}%</div>
                        <div class="small fw-bold text-secondary text-uppercase">Active</div>
                    </div>
                </div>

                <div class="status-legend w-100">
                    <div class="status-legend-item">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background: #198754;"></span>
                            <span class="small fw-bold text-secondary text-uppercase">Active</span>
                        </div>
                        <div class="setup-value">{{ $activeUsers }}</div>
                    </div>
                    <div class="status-legend-item">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background: #dc3545;"></span>
                            <span class="small fw-bold text-secondary text-uppercase">Inactive</span>
                        </div>
                        <div class="setup-value">{{ $inactiveUsers }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-card p-4">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                <div>
                    <p class="stat-label mb-2">Academic Setup</p>
                    <h2 class="h4 mb-0" style="color: var(--psu-navy);">{{ $activeSemester ?? 'No active semester set' }}</h2>
                </div>
                <span class="icon-tile"><span class="material-symbols-outlined">calendar_month</span></span>
            </div>

            <div class="setup-grid">
                <div class="setup-item">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Colleges</p>
                    <div class="setup-value">{{ $totalColleges }}</div>
                </div>
                <div class="setup-item">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Departments</p>
                    <div class="setup-value">{{ $totalDepartments }}</div>
                </div>
                <div class="setup-item">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Programs</p>
                    <div class="setup-value">{{ $activePrograms }} / {{ $totalPrograms }}</div>
                </div>
                <div class="setup-item">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Subjects</p>
                    <div class="setup-value">{{ $activeSubjects }} / {{ $totalSubjects }}</div>
                </div>
                <div class="setup-item">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Active Semester Subjects</p>
                    <div class="setup-value">{{ $activeSemesterSubjectCount }}</div>
                </div>
                <div class="setup-item">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Managed Roles</p>
                    <div class="setup-value">{{ $totalAdminDeans + $totalDepartmentChairs }}</div>
                </div>
            </div>
        </section>
    </div>

    {{-- Student distribution by program --}}
    <section class="dashboard-card p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <p class="stat-label mb-2">Students per Program</p>
                <h2 class="h4 mb-0" style="color: var(--psu-navy);">Top Program Distribution</h2>
            </div>
            <span class="badge text-bg-light border rounded-1">{{ $programStudentRows->count() }} shown</span>
        </div>

        @forelse ($programStudentRows as $program)
            <div class="program-row">
                <div>
                    <div class="fw-bold" style="color: var(--psu-navy);">{{ $program['name'] }}</div>
                    <div class="small text-secondary">{{ $program['college'] }}</div>
                </div>
                <div class="program-track" aria-hidden="true">
                    <div class="program-fill" style="--bar-width: {{ $program['percentage'] }}%;"></div>
                </div>
                <div class="fw-bold text-end" style="color: var(--psu-navy);">{{ $program['students'] }}</div>
            </div>
        @empty
            <div class="text-center text-secondary py-4">
                No programs or student accounts yet.
            </div>
        @endforelse
    </section>
@endsection
