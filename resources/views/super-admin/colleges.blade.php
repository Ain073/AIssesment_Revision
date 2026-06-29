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

            <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
                <button class="btn btn-outline-psu d-flex align-items-center gap-2" data-bs-target="#departmentModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">add_business</span>
                    Add Department
                </button>
                <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#collegeModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">add</span>
                    Add College
                </button>
            </div>

            <ul class="nav nav-tabs mb-3" id="directoryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active px-4 py-3" data-bs-target="#collegesPane" data-bs-toggle="tab" type="button" role="tab">Colleges</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link px-4 py-3" data-bs-target="#departmentsPane" data-bs-toggle="tab" type="button" role="tab">Departments</button>
                </li>
            </ul>

            <div class="tab-content">
                <section class="tab-pane fade show active directory-card shadow-sm" id="collegesPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Colleges List</h3>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white" type="button"><span class="material-symbols-outlined">filter_list</span></button>
                            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white" type="button"><span class="material-symbols-outlined">download</span></button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
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
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="icon-box"><span class="material-symbols-outlined">account_balance</span></span>
                                                <span class="fw-bold" style="color: var(--psu-navy);">{{ $college->college_name }}</span>
                                            </div>
                                        </td>
                                        <td class="fw-semibold">
                                            {{ $college->departments_count }} {{ $college->departments_count === 1 ? 'Department' : 'Departments' }}
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"><span class="material-symbols-outlined fs-6">visibility</span></button>
                                            <button class="btn btn-sm btn-outline-secondary" type="button"><span class="material-symbols-outlined fs-6">edit</span></button>
                                            <form
                                                action="{{ route('super-admin.colleges.destroy', $college) }}"
                                                class="d-inline"
                                                method="POST"
                                                data-confirm-message="{{ 'Delete '.$college->college_name.'? Related departments and programs under this college will also be removed.' }}"
                                                onsubmit="return confirm(this.dataset.confirmMessage);"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                                    <span class="material-symbols-outlined fs-6">delete</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="3">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">account_balance</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No colleges yet</h4>
                                            <p class="text-secondary mb-4">Create your first college to start setting up academic units and departments.</p>
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
                        <span class="badge text-bg-primary rounded-1">1</span>
                    </div>
                </section>

                <section class="tab-pane fade directory-card shadow-sm" id="departmentsPane" role="tabpanel">
                    <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                        <h3 class="h4 mb-0">Departments List</h3>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white" type="button"><span class="material-symbols-outlined">filter_list</span></button>
                            <button class="btn btn-sm btn-light bg-white bg-opacity-10 border-0 text-white" type="button"><span class="material-symbols-outlined">download</span></button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
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
                                        <td class="fw-bold" style="color: var(--psu-navy);">{{ $department->dept_name }}</td>
                                        <td>{{ $department->college?->college_name }}</td>
                                        <td>
                                            {{ $department->instructor_profiles_count }}
                                            {{ $department->instructor_profiles_count === 1 ? 'Instructor' : 'Instructors' }}
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"><span class="material-symbols-outlined fs-6">visibility</span></button>
                                            <button class="btn btn-sm btn-outline-secondary" type="button"><span class="material-symbols-outlined fs-6">edit</span></button>
                                            <form
                                                action="{{ route('super-admin.departments.destroy', $department) }}"
                                                class="d-inline"
                                                method="POST"
                                                data-confirm-message="{{ 'Delete '.$department->dept_name.'? This may remove the department assignment from linked instructor profiles.' }}"
                                                onsubmit="return confirm(this.dataset.confirmMessage);"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                                    <span class="material-symbols-outlined fs-6">delete</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center py-5" colspan="4">
                                            <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">add_business</span></div>
                                            <h4 class="h4" style="color: var(--psu-navy);">No departments yet</h4>
                                            <p class="text-secondary mb-4">Add a department after creating at least one college.</p>
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
                        <span class="badge text-bg-primary rounded-1">1</span>
                    </div>
                </section>

            </div>

<div class="modal fade" id="collegeModal" tabindex="-1" aria-labelledby="collegeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.colleges.store') }}" class="modal-content" method="POST">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title h4" id="collegeModalLabel">New College</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-bold text-uppercase small" for="college_name">College Name</label>
                    <input class="form-control form-control-lg" id="college_name" name="college_name" placeholder="e.g. College of Information Technology" required type="text" value="{{ old('college_name') }}">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit">Save College</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="departmentModal" tabindex="-1" aria-labelledby="departmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.departments.store') }}" class="modal-content" method="POST">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title h4" id="departmentModalLabel">New Department</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="college_id">College</label>
                        <select class="form-select form-select-lg" id="college_id" name="college_id" required @disabled($colleges->isEmpty())>
                            @forelse ($colleges as $college)
                                <option value="{{ $college->college_id }}" @selected(old('college_id') == $college->college_id)>{{ $college->college_name }}</option>
                            @empty
                                <option>No colleges available yet</option>
                            @endforelse
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold text-uppercase small" for="dept_name">Department Name</label>
                        <input class="form-control form-control-lg" id="dept_name" name="dept_name" placeholder="e.g. Department of Information Technology" required type="text" value="{{ old('dept_name') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($colleges->isEmpty())>Save Department</button>
                </div>
            </form>
        </div>
    </div>
@endsection
