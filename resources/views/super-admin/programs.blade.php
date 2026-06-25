@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => false],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => false],
        ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active' => true],
        ['label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('super-admin.subjects'), 'active' => false],
        ['label' => 'Deans & Department Chairs', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active' => false],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => false],
    ];
@endphp

@section('title', 'Programs | AIssessment Super Admin')
@section('header', 'Programs')

@push('styles')
    <style>
        .stat-card,
        .directory-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .directory-header,
        .modal-header {
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

        .programs-table {
            min-width: 920px;
        }

        .programs-table .count-cell {
            text-align: center;
        }

        .action-buttons {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }

        .action-buttons .btn {
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .program-detail-label {
            color: var(--psu-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .program-toolbar .college-filter-select {
            min-width: min(360px, 100%);
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
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Total Programs</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalPrograms }}</span>
                    <span class="small text-secondary">Current degree offerings</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Active Programs</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $activePrograms }}</span>
                    <span class="small text-secondary">Available to map with students and subjects</span>
                </div>
            </div>
        </div>
    </div>

    <div class="program-toolbar d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form action="{{ route('super-admin.programs') }}" method="GET">
            <label class="form-label small fw-bold text-uppercase mb-1" for="college-filter">View College</label>
            <select class="form-select college-filter-select" id="college-filter" name="college" onchange="this.form.submit()">
                <option value="">All Colleges</option>
                @foreach ($colleges as $college)
                    <option value="{{ $college->college_id }}" @selected($selectedCollegeId === $college->college_id)>
                        {{ $college->college_name }}
                    </option>
                @endforeach
            </select>
            <noscript>
                <button class="btn btn-outline-primary mt-2" type="submit">View</button>
            </noscript>
        </form>

        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#programModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">add</span>
            Add Program
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Programs List</h3>
            <span class="small text-white-50">Managed separately from colleges</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 programs-table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>College</th>
                        <th class="count-cell">Students</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                        <tr>
                            <td class="fw-bold" style="color: var(--psu-navy);">{{ $program->program_name }}</td>
                            <td>{{ $program->college?->college_name ?? 'Not assigned' }}</td>
                            <td class="count-cell">{{ $program->student_profiles_count }}</td>
                            <td>
                                <span class="badge {{ $program->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $program->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-secondary d-inline-flex" data-bs-target="#viewProgramModal{{ $program->program_id }}" data-bs-toggle="modal" type="button" title="View program" aria-label="View {{ $program->program_name }}">
                                        <span class="material-symbols-outlined fs-6">visibility</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary d-inline-flex" data-bs-target="#editProgramModal{{ $program->program_id }}" data-bs-toggle="modal" type="button" title="Edit program" aria-label="Edit {{ $program->program_name }}">
                                        <span class="material-symbols-outlined fs-6">edit</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger d-inline-flex" data-bs-target="#deleteProgramModal{{ $program->program_id }}" data-bs-toggle="modal" type="button" title="Delete program" aria-label="Delete {{ $program->program_name }}">
                                        <span class="material-symbols-outlined fs-6">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="5">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">school</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No programs yet</h4>
                                <p class="text-secondary mb-4">Create the first program to organize students and subject mappings.</p>
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#programModal" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Add First Program
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
            <span class="small text-secondary">Showing {{ $programs->count() }} {{ $programs->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Programs are now managed on their own page</span>
        </div>
    </section>

    <div class="modal fade" id="programModal" tabindex="-1" aria-labelledby="programModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.programs.store') }}" class="modal-content" method="POST">
                @csrf
                <input name="is_active" type="hidden" value="0">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="programModalLabel">New Program</h3>
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
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="program_name">Program Name</label>
                        <input class="form-control form-control-lg" id="program_name" name="program_name" placeholder="e.g. Bachelor of Science in Hospitality Management" required type="text" value="{{ old('program_name') }}">
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                        <label class="form-check-label fw-semibold" for="is_active">Program is active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($colleges->isEmpty())>Save Program</button>
                </div>
            </form>
        </div>
    </div>

    @foreach ($programs as $program)
        <div class="modal fade" id="viewProgramModal{{ $program->program_id }}" tabindex="-1" aria-labelledby="viewProgramModalLabel{{ $program->program_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="viewProgramModalLabel{{ $program->program_id }}">Program Details</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="program-detail-label mb-1">Program</div>
                            <div class="fw-bold" style="color: var(--psu-navy);">{{ $program->program_name }}</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="program-detail-label mb-1">College</div>
                                <div>{{ $program->college?->college_name ?? 'Not assigned' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="program-detail-label mb-1">Status</div>
                                <span class="badge {{ $program->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $program->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <div class="program-detail-label mb-1">Students</div>
                                <div class="fw-bold">{{ $program->student_profiles_count }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="program-detail-label mb-1">Subject Mappings</div>
                                <div class="fw-bold">{{ $program->subject_programs_count }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editProgramModal{{ $program->program_id }}" tabindex="-1" aria-labelledby="editProgramModalLabel{{ $program->program_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('super-admin.programs.update', ['program' => $program, 'college' => $selectedCollegeId]) }}" class="modal-content" method="POST">
                    @csrf
                    @method('PUT')
                    <input name="is_active" type="hidden" value="0">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="editProgramModalLabel{{ $program->program_id }}">Edit Program</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="edit_college_id_{{ $program->program_id }}">College</label>
                            <select class="form-select form-select-lg" id="edit_college_id_{{ $program->program_id }}" name="college_id" required>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->college_id }}" @selected(old('college_id', $program->college_id) == $college->college_id)>{{ $college->college_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="edit_program_name_{{ $program->program_id }}">Program Name</label>
                            <input class="form-control form-control-lg" id="edit_program_name_{{ $program->program_id }}" name="program_name" required type="text" value="{{ old('program_name', $program->program_name) }}">
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" id="edit_is_active_{{ $program->program_id }}" name="is_active" type="checkbox" value="1" @checked(old('is_active', $program->is_active ? '1' : '0') === '1')>
                            <label class="form-check-label fw-semibold" for="edit_is_active_{{ $program->program_id }}">Program is active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                        <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="deleteProgramModal{{ $program->program_id }}" tabindex="-1" aria-labelledby="deleteProgramModalLabel{{ $program->program_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('super-admin.programs.destroy', ['program' => $program, 'college' => $selectedCollegeId]) }}" class="modal-content" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="deleteProgramModalLabel{{ $program->program_id }}">Delete Program</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Delete <strong>{{ $program->program_name }}</strong>?</p>
                        <p class="text-secondary mb-0">Programs with linked students or subject mappings cannot be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger px-4" type="submit">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
