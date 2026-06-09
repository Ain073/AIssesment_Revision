@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => true],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => false],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => false],
    ];
@endphp

@section('title', 'Dashboard | AIssessment Super Admin')
@section('header', 'Super Admin Dashboard')

@push('styles')
    <style>
        .dashboard-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
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
    </style>
@endpush

@section('content')
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
@endsection
