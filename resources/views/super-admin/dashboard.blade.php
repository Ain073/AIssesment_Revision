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
    <div class="row g-3 mb-4 dashboard-stat-row">
        <div class="col-6 col-lg-3">
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
        <div class="col-6 col-lg-3">
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
        <div class="col-6 col-lg-3">
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
        <div class="col-6 col-lg-3">
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
            <div class="program-filter-group">
                <label class="program-filter-label" for="programCollegeFilter">College</label>
                <select class="form-select form-select-sm program-college-filter" id="programCollegeFilter">
                    <option value="">All colleges</option>
                    @foreach ($programStudentColleges as $college)
                        <option value="{{ $college->college_id }}">{{ $college->college_name }}</option>
                    @endforeach
                </select>
                <span class="badge text-bg-light border rounded-1" id="programShownCount">{{ $programStudentRows->count() }} shown</span>
            </div>
        </div>

        @forelse ($programStudentRows as $program)
            <div class="program-row" data-college-id="{{ $program['college_id'] }}" data-students="{{ $program['students'] }}">
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

        <div class="text-center text-secondary py-4 d-none" id="programFilterEmpty">
            No programs found for this college.
        </div>
    </section>
@endsection

@push('scripts')
    @include('super-admin.dashboard-page-code')
@endpush
