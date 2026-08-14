@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $showTopbarSearch = true;
    $topbarSearchPlaceholder = 'Search records...';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => request()->routeIs('super-admin.dashboard')],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => request()->routeIs('super-admin.colleges')],
        ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active' => request()->routeIs('super-admin.programs')],
        ['label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('super-admin.subjects'), 'active' => request()->routeIs('super-admin.subjects')],
        ['label' => 'Deans & Department Chairs', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active' => request()->routeIs('super-admin.roles')],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => request()->routeIs('super-admin.users')],
    ];
@endphp

@section('title', 'Colleges & Departments | AIssessment Super Admin')
@section('header', 'Colleges & Departments')

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
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
            border-radius: 0.25rem;
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

        .modal-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
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

        .action-button {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .delete-warning-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fff1f2;
            color: #dc3545;
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

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Page actions --}}
            <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4 super-admin-toolbar">
                <button class="btn btn-outline-psu d-flex align-items-center gap-2" data-bs-target="#departmentModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">add_business</span>
                    Add Department
                </button>
                <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#collegeModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">add</span>
                    Add College
                </button>
            </div>

            <div class="table-switch-tabs" id="directoryTabs" role="tablist">
                <button class="btn btn-outline-primary table-switch-button active d-inline-flex align-items-center gap-2" data-bs-target="#collegesPane" data-bs-toggle="tab" type="button" role="tab">
                    <span class="material-symbols-outlined fs-5">account_balance</span>
                    Colleges
                    <span class="table-switch-count">{{ $colleges->count() }}</span>
                </button>
                <button class="btn btn-outline-primary table-switch-button d-inline-flex align-items-center gap-2" data-bs-target="#departmentsPane" data-bs-toggle="tab" type="button" role="tab">
                    <span class="material-symbols-outlined fs-5">apartment</span>
                    Departments
                    <span class="table-switch-count">{{ $departments->count() }}</span>
                </button>
            </div>

            <div class="tab-content">
                {{-- Colleges table --}}
                <section class="tab-pane fade show active directory-card shadow-sm" id="collegesPane" role="tabpanel">
                    <div class="directory-header px-4 py-3">
                        <h3 class="h4 mb-0">Colleges List</h3>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 mobile-card-table">
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
                                        <td class="mobile-primary-cell" data-label="College Name">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="icon-box"><span class="material-symbols-outlined">account_balance</span></span>
                                                <span class="fw-bold" style="color: var(--psu-navy);">{{ $college->college_name }}</span>
                                            </div>
                                        </td>
                                        <td class="fw-semibold" data-label="Departments">
                                            {{ $college->departments_count }} {{ $college->departments_count === 1 ? 'Department' : 'Departments' }}
                                        </td>
                                        <td class="text-end" data-label="Actions">
                                            <button class="btn btn-sm record-action-trigger" data-bs-target="#viewCollegeModal{{ $college->college_id }}" data-bs-toggle="modal" type="button" aria-label="Manage {{ $college->college_name }}">
                                                <span class="material-symbols-outlined fs-6">visibility</span>
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5 mobile-empty-cell" colspan="3">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">account_balance</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No colleges yet</h4>
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
                    </div>
                </section>

                {{-- Departments table --}}
                <section class="tab-pane fade directory-card shadow-sm" id="departmentsPane" role="tabpanel">
                    <div class="directory-header px-4 py-3">
                        <h3 class="h4 mb-0">Departments List</h3>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 mobile-card-table">
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th>College</th>
                                    <th>Instructors</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($departments as $department)
                                    <tr>
                                        <td class="fw-bold mobile-primary-cell" data-label="Department Name" style="color: var(--psu-navy);">{{ $department->dept_name }}</td>
                                        <td data-label="College">{{ $department->college?->college_name }}</td>
                                        <td data-label="Instructors">
                                            {{ $department->instructor_profiles_count }}
                                            {{ $department->instructor_profiles_count === 1 ? 'Instructor' : 'Instructors' }}
                                        </td>
                                        <td class="text-end" data-label="Actions">
                                            <button class="btn btn-sm record-action-trigger" data-bs-target="#viewDepartmentModal{{ $department->department_id }}" data-bs-toggle="modal" type="button" aria-label="Manage {{ $department->dept_name }}">
                                                <span class="material-symbols-outlined fs-6">visibility</span>
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5 mobile-empty-cell" colspan="4">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">add_business</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No departments yet</h4>
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
                    </div>
                </section>

            </div>

    @include('super-admin.colleges.create-college-form')
    @include('super-admin.colleges.create-department-form')
    @include('super-admin.colleges.edit-delete-popups')
@endsection
