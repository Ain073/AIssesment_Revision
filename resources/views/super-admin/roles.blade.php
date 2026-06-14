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
        .stat-card,
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
            border-bottom-color: var(--psu-gold);
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
@endsection

@push('scripts')
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
@endpush
