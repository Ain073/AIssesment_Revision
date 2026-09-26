@extends('layouts.portal')

@php
    $portalSubtitle = 'Admin Portal';
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
        ['label' => 'Passing Rates', 'icon' => 'percent', 'href' => route('super-admin.passing-rates'), 'active' => request()->routeIs('super-admin.passing-rates')],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => request()->routeIs('super-admin.users')],
        ['label' => 'Audit Trail', 'icon' => 'fact_check', 'href' => route('super-admin.audit-logs'), 'active' => request()->routeIs('super-admin.audit-logs')],
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

    <div class="designation-action-row d-flex align-items-center justify-content-end gap-2">
        <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" data-bs-target="#deanHistoryModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">history</span>
            Designation History
        </button>
        <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#grantAdminDeanModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">add</span>
            Designation
        </button>
    </div>

    <section class="directory-card shadow-sm">
                    <div class="directory-header px-4 py-3">
                        <h3 class="h4 mb-0">Dean Designation</h3>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 mobile-card-table">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Email</th>
                                    <th>College</th>
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
                                        <td data-label="College">
                                            <div class="fw-semibold">{{ $user->instructorProfile?->department?->college?->college_name ?? 'Not assigned' }}</div>
                                            <div class="small text-secondary">{{ $user->instructorProfile?->department?->dept_name ?? 'No department' }}</div>
                                        </td>
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
                                        <td class="text-center py-5 mobile-empty-cell" colspan="5">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">supervisor_account</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No Dean designation yet</h4>
                                            <p class="text-secondary mb-4">Assign a teacher account when you are ready to delegate dean-level access.</p>
                                            <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#grantAdminDeanModal" data-bs-toggle="modal" type="button">
                                                <span class="material-symbols-outlined fs-5">add</span>
                                                Designation
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

    {{-- Dean Designation History Modal --}}
    <div class="modal fade" id="deanHistoryModal" tabindex="-1" aria-labelledby="deanHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4 mb-1" id="deanHistoryModalLabel">Dean Designation History & Term Records</h3>
                        <p class="small text-white-50 mb-0">Institutional timeline and past appointments of College Deans</p>
                    </div>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 mobile-card-table">
                            <thead>
                                <tr>
                                    <th>Instructor</th>
                                    <th>College</th>
                                    <th>Academic Year</th>
                                    <th>Term Period</th>
                                    <th>Status</th>
                                    <th>Designated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($designationHistory as $history)
                                    @php
                                        $instructorUser = $history->instructor?->user;
                                        $effectivity = $history->effectivity_date ? \Carbon\Carbon::parse($history->effectivity_date)->format('M d, Y') : '—';
                                        $endDate = $history->end_date ? \Carbon\Carbon::parse($history->end_date)->format('M d, Y') : 'Present';
                                    @endphp
                                    <tr>
                                        <td class="mobile-primary-cell" data-label="Instructor">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="avatar">{{ strtoupper(substr($instructorUser?->displayName() ?? 'U', 0, 1)) }}</span>
                                                <div>
                                                    <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $instructorUser?->displayName() ?? 'Unknown Teacher' }}</p>
                                                    <p class="small text-secondary mb-0">{{ $instructorUser?->email ?? '' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="College">{{ $history->college?->college_name ?? $history->department?->college?->college_name ?? '—' }}</td>
                                        <td data-label="Academic Year">
                                            <span class="badge bg-light text-dark border">{{ $history->academic_year }}</span>
                                        </td>
                                        <td data-label="Term Period">
                                            <div class="small fw-semibold text-secondary">
                                                {{ $effectivity }} &rarr; <span class="{{ $history->status === 'active' ? 'text-success fw-bold' : '' }}">{{ $endDate }}</span>
                                            </div>
                                        </td>
                                        <td data-label="Status">
                                            <span class="badge {{ $history->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                                {{ ucfirst($history->status) }}
                                            </span>
                                        </td>
                                        <td data-label="Designated By">
                                            <span class="small text-secondary">{{ $history->designatedByUser?->displayName() ?? 'System / Admin' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-4 mobile-empty-cell" colspan="6">
                                            <p class="small text-secondary mb-0">No past dean designation records found.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                </div>
            </div>
        </div>
    </div>

    @include('super-admin.roles.add-remove-access-popups')
@endsection

@push('scripts')
    @include('super-admin.roles.role-page-code')
@endpush
