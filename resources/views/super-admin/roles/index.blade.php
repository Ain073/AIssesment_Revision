@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $showTopbarSearch = true;
    $topbarSearchPlaceholder = 'Search teachers...';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => request()->routeIs('super-admin.dashboard')],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => request()->routeIs('super-admin.colleges')],
        ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active' => request()->routeIs('super-admin.programs')],
        ['label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('super-admin.subjects'), 'active' => request()->routeIs('super-admin.subjects')],
        ['label' => 'Deans & Department Chairs', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active' => request()->routeIs('super-admin.roles')],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => request()->routeIs('super-admin.users')],
    ];
@endphp

@section('title', 'Deans & Department Chairs | AIssessment Super Admin')
@section('header', 'Deans & Department Chairs')

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

        .btn-outline-psu:hover,
        .btn-outline-psu:focus {
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

    <div class="table-switch-tabs" id="authorizationTabs" role="tablist">
        <button class="btn btn-outline-primary table-switch-button active d-inline-flex align-items-center gap-2" data-bs-target="#adminDeanPane" data-bs-toggle="tab" type="button" role="tab">
            <span class="material-symbols-outlined fs-5">admin_panel_settings</span>
            Admin/Dean
            <span class="table-switch-count">{{ $adminDeans->count() }}</span>
        </button>
        <button class="btn btn-outline-primary table-switch-button d-inline-flex align-items-center gap-2" data-bs-target="#departmentChairPane" data-bs-toggle="tab" type="button" role="tab">
            <span class="material-symbols-outlined fs-5">supervisor_account</span>
            Department Chair
            <span class="table-switch-count">{{ $departmentChairs->count() }}</span>
        </button>
    </div>

    <div class="tab-content">
        {{-- Admin/Dean table --}}
        <section class="tab-pane fade show active directory-card shadow-sm" id="adminDeanPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Admin/Dean Authorization</h3>
                        <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white d-inline-flex align-items-center gap-1" data-bs-target="#grantAdminDeanModal" data-bs-toggle="modal" type="button">
                            <span class="material-symbols-outlined fs-6">person_add</span>
                            Add Authorization
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 mobile-card-table">
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
                                        <td class="mobile-primary-cell" data-label="Teacher">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div>
                                                    <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $user->displayName() }}</p>
                                                    <p class="small text-secondary mb-0">Teacher Account</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Email">{{ $user->email }}</td>
                                        <td data-label="Status">
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end" data-label="Action">
                                            <button class="btn btn-sm record-action-trigger" data-bs-target="#viewAdminDeanModal{{ $user->id }}" data-bs-toggle="modal" type="button" aria-label="Manage {{ $user->displayName() }}">
                                                <span class="material-symbols-outlined fs-6">visibility</span>
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5 mobile-empty-cell" colspan="4">
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

        {{-- Department Chair table --}}
        <section class="tab-pane fade directory-card shadow-sm" id="departmentChairPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Department Chair Authorization</h3>
                        <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white d-inline-flex align-items-center gap-1" data-bs-target="#grantDepartmentChairModal" data-bs-toggle="modal" type="button">
                            <span class="material-symbols-outlined fs-6">person_add</span>
                            Add Authorization
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 mobile-card-table">
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
                                        <td class="mobile-primary-cell" data-label="Teacher">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($user->displayName(), 0, 1)) }}</span>
                                                <div>
                                                    <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $user->displayName() }}</p>
                                                    <p class="small text-secondary mb-0">Teacher Account</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Email">{{ $user->email }}</td>
                                        <td data-label="Status">
                                            <span class="badge {{ $user->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end" data-label="Action">
                                            <button class="btn btn-sm record-action-trigger" data-bs-target="#viewDepartmentChairModal{{ $user->id }}" data-bs-toggle="modal" type="button" aria-label="Manage {{ $user->displayName() }}">
                                                <span class="material-symbols-outlined fs-6">visibility</span>
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5 mobile-empty-cell" colspan="4">
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

    @include('super-admin.roles.add-remove-access-popups')
@endsection

@push('scripts')
    @include('super-admin.roles.role-page-code')
@endpush
