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
        .dashboard-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }

    </style>
@endpush

@section('content')
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
        <div class="col-lg-4">
            <section class="dashboard-card p-4 h-100">
                <h3 class="h4 mb-3" style="color: var(--psu-navy);">Quick Actions</h3>
                <div class="d-grid gap-2">
                    <a class="btn btn-psu" href="{{ route('super-admin.colleges') }}">Add College</a>
                    <a class="btn btn-outline-secondary" href="{{ route('super-admin.colleges') }}">Add Department</a>
                    <a class="btn btn-outline-secondary" href="{{ route('super-admin.programs') }}">Add Program</a>
                    <a class="btn btn-outline-secondary" href="{{ route('super-admin.subjects') }}">Add Subject</a>
                    <a class="btn btn-outline-secondary" href="{{ route('super-admin.users') }}">Manage Users</a>
                </div>
            </section>
        </div>
    </div>
@endsection
