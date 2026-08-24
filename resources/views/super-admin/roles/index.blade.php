@extends('layouts.portal')

@php
    $portalSubtitle = 'Admin Panel';
    $profileInitials = 'A';
    $profileName = 'Admin';
    $profileMeta = 'Admin Account';
    $showTopbarSearch = true;
    $topbarSearchPlaceholder = 'Search teachers...';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => request()->routeIs('super-admin.dashboard')],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => request()->routeIs('super-admin.colleges')],
        ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active' => request()->routeIs('super-admin.programs')],
        ['label' => 'Dean Designation', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active' => request()->routeIs('super-admin.roles')],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => request()->routeIs('super-admin.users')],
    ];
@endphp

@section('title', 'Dean Designation | AIssessment Admin')
@section('header', 'Dean Designation')


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

    <section class="directory-card shadow-sm">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Dean Designation</h3>
                        <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white d-inline-flex align-items-center gap-1" data-bs-target="#grantAdminDeanModal" data-bs-toggle="modal" type="button">
                            <span class="material-symbols-outlined fs-6">person_add</span>
                            Add Designation
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
                                            <button class="btn btn-sm record-action-trigger" data-bs-target="#viewAdminDeanModal{{ $user->id }}" data-bs-toggle="modal" type="button" aria-label="View {{ $user->displayName() }}">
                                                <span class="material-symbols-outlined fs-6">visibility</span>
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5 mobile-empty-cell" colspan="4">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">supervisor_account</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No Dean designation yet</h4>
                                            <p class="text-secondary mb-4">Assign a teacher account when you are ready to delegate dean-level access.</p>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#grantAdminDeanModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">person_add</span>
                                                Add Designation
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

    @include('super-admin.roles.add-remove-access-popups')
@endsection

@push('scripts')
    @include('super-admin.roles.role-page-code')
@endpush
