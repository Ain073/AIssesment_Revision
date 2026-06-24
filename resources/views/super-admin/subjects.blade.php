@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active' => false],
        ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active' => false],
        ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active' => false],
        ['label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('super-admin.subjects'), 'active' => true],
        ['label' => 'Deans & Department Chairs', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active' => false],
        ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active' => false],
    ];
@endphp

@section('title', 'Subjects | AIssessment Super Admin')
@section('header', 'Subjects')

@push('styles')
    <style>
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

        .subjects-table {
            width: 100%;
            table-layout: fixed;
        }

        .subjects-table thead th,
        .subjects-table tbody td {
            padding: 0.85rem 0.65rem;
            overflow-wrap: anywhere;
        }

        .subjects-table thead th {
            font-size: 0.72rem;
            letter-spacing: 0.04em;
        }

        .subjects-table-wrap {
            overflow-x: visible;
        }

        .subject-toolbar .program-filter-select {
            min-width: min(360px, 100%);
        }

        .subject-toolbar .compact-filter-select {
            min-width: 170px;
        }

        .academic-term-toolbar {
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--psu-line);
        }

        .active-term-value {
            color: var(--psu-navy);
            font-size: 1.05rem;
            font-weight: 700;
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

        .subject-detail-label {
            color: var(--psu-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        @media (max-width: 767.98px) {
            .subjects-table,
            .subjects-table tbody,
            .subjects-table tr,
            .subjects-table td {
                display: block;
                width: 100%;
            }

            .subjects-table colgroup,
            .subjects-table thead {
                display: none;
            }

            .subjects-table tbody tr {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid var(--psu-line);
            }

            .subjects-table tbody tr:last-child {
                border-bottom: 0;
            }

            .subjects-table tbody td {
                display: grid;
                grid-template-columns: minmax(105px, 35%) minmax(0, 1fr);
                gap: 0.75rem;
                align-items: center;
                padding: 0.45rem 0;
                border: 0;
                text-align: left !important;
            }

            .subjects-table tbody td::before {
                content: attr(data-label);
                color: var(--psu-muted);
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
            }

            .subjects-table tbody td.subject-empty-cell {
                display: block;
                padding: 2rem 0 !important;
                text-align: center !important;
            }

            .subjects-table tbody td.subject-empty-cell::before {
                content: none;
            }

            .subjects-table .action-buttons {
                justify-content: flex-start;
            }
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

    <div class="academic-term-toolbar d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <div class="small fw-bold text-secondary text-uppercase mb-1">Active Semester</div>
            <div class="active-term-value d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">calendar_month</span>
                {{ $activeSemester ?? 'Not set' }}
            </div>
        </div>

        <form action="{{ route('super-admin.subjects.semester.activate') }}" class="d-flex flex-wrap align-items-end gap-2" method="POST">
            @csrf
            <div>
                <label class="form-label small fw-bold text-uppercase mb-1" for="active-semester">Semester</label>
                <select class="form-select compact-filter-select" id="active-semester" name="semester" required>
                    @foreach (['First Semester', 'Second Semester', 'Summer'] as $semester)
                        <option value="{{ $semester }}" @selected($activeSemester === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-psu d-inline-flex align-items-center gap-2" type="submit">
                <span class="material-symbols-outlined fs-5">check_circle</span>
                Activate
            </button>
        </form>
    </div>

    <div class="subject-toolbar d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form action="{{ route('super-admin.subjects') }}" class="d-flex flex-wrap align-items-end gap-4" method="GET">
            <div>
                <label class="form-label small fw-bold text-uppercase mb-1" for="program-filter">View Program</label>
                <select class="form-select program-filter-select" id="program-filter" name="program" onchange="this.form.submit()">
                    <option value="">All Programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->program_id }}" @selected($selectedProgramId === $program->program_id)>
                            {{ $program->program_name }} - {{ $program->college?->college_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small fw-bold text-uppercase mb-1" for="year-level-filter">Year Level</label>
                <select class="form-select compact-filter-select" id="year-level-filter" name="year_level" onchange="this.form.submit()">
                    <option value="">All Year Levels</option>
                    @foreach ([1, 2, 3, 4] as $yearLevel)
                        <option value="{{ $yearLevel }}" @selected($selectedYearLevel === $yearLevel)>Year {{ $yearLevel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small fw-bold text-uppercase mb-1" for="semester-filter">Semester</label>
                <select class="form-select compact-filter-select" id="semester-filter" name="semester" onchange="this.form.submit()">
                    <option value="">All Semesters</option>
                    @foreach (['First Semester', 'Second Semester', 'Summer'] as $semester)
                        <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <noscript>
                <button class="btn btn-outline-primary" type="submit">View</button>
            </noscript>
        </form>

        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#subjectModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">add</span>
            Add Subject
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Subjects by Program</h3>
            <span class="small text-white-50">Central catalog managed separately from programs</span>
        </div>

        <div class="table-responsive subjects-table-wrap">
            <table class="table table-hover mb-0 subjects-table">
                <colgroup>
                    <col style="width: 12%;">
                    <col style="width: 22%;">
                    <col style="width: 22%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                    <col style="width: 9%;">
                    <col style="width: 11%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Program</th>
                        <th class="text-center">Year Level</th>
                        <th>Semester</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjectMappings as $mapping)
                        @php
                            $mappingIsActive = $mapping->subject?->is_active && $activeSemester === $mapping->semester;
                        @endphp
                        <tr>
                            <td class="fw-bold" data-label="Subject Code" style="color: var(--psu-navy);">{{ $mapping->subject?->subject_code }}</td>
                            <td data-label="Subject Name">{{ $mapping->subject?->subject_name }}</td>
                            <td data-label="Program">
                                <div class="fw-semibold">{{ $mapping->program?->program_name ?? 'Not assigned' }}</div>
                                <div class="small text-secondary">{{ $mapping->program?->college?->college_name ?? 'No college' }}</div>
                            </td>
                            <td class="text-center" data-label="Year Level">{{ $mapping->year_level }}</td>
                            <td data-label="Semester">{{ $mapping->semester }}</td>
                            <td class="text-center" data-label="Status">
                                <span class="badge {{ $mappingIsActive ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $mappingIsActive ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-center" data-label="Actions">
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-outline-secondary d-inline-flex" data-bs-target="#viewSubjectModal{{ $mapping->subject_program_id }}" data-bs-toggle="modal" type="button" title="View subject" aria-label="View {{ $mapping->subject?->subject_code }}">
                                        <span class="material-symbols-outlined fs-6">visibility</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary d-inline-flex" data-bs-target="#editSubjectModal{{ $mapping->subject_program_id }}" data-bs-toggle="modal" type="button" title="Edit subject" aria-label="Edit {{ $mapping->subject?->subject_code }}">
                                        <span class="material-symbols-outlined fs-6">edit</span>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger d-inline-flex" data-bs-target="#deleteSubjectModal{{ $mapping->subject_program_id }}" data-bs-toggle="modal" type="button" title="Delete subject" aria-label="Delete {{ $mapping->subject?->subject_code }}">
                                        <span class="material-symbols-outlined fs-6">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5 subject-empty-cell" colspan="7">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">menu_book</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">
                                    {{ $hasSubjectFilters ? 'No subjects match these filters' : 'No subject mappings yet' }}
                                </h4>
                                <p class="text-secondary mb-4">
                                    {{ $hasSubjectFilters ? 'Try another program, year level, or semester.' : 'Create your first subject and assign it to a program.' }}
                                </p>
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#subjectModal" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Add First Subject
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #eff4ff;">
            <span class="small text-secondary">Showing {{ $subjectMappings->count() }} {{ $subjectMappings->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Subjects are now managed on their own page</span>
        </div>
    </section>

    <div class="modal fade" id="subjectModal" tabindex="-1" aria-labelledby="subjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('super-admin.subjects.store') }}" class="modal-content" method="POST">
                @csrf
                <input name="is_active" type="hidden" value="0">
                <div class="modal-header">
                    <h3 class="modal-title h4" id="subjectModalLabel">New Subject</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="program_id">Program</label>
                        <select class="form-select form-select-lg" id="program_id" name="program_id" required @disabled($programs->isEmpty())>
                            @forelse ($programs as $program)
                                <option value="{{ $program->program_id }}" @selected(old('program_id', $selectedProgramId) == $program->program_id)>{{ $program->program_name }} - {{ $program->college?->college_name }}</option>
                            @empty
                                <option>No programs available yet</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="subject_code">Subject Code</label>
                        <input class="form-control form-control-lg" id="subject_code" name="subject_code" placeholder="e.g. IT 101" required type="text" value="{{ old('subject_code') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-uppercase small" for="subject_name">Subject Name</label>
                        <input class="form-control form-control-lg" id="subject_name" name="subject_name" placeholder="e.g. Introduction to Computing" required type="text" value="{{ old('subject_name') }}">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="year_level">Year Level</label>
                            <select class="form-select form-select-lg" id="year_level" name="year_level" required>
                                @foreach ([1, 2, 3, 4] as $yearLevel)
                                    <option value="{{ $yearLevel }}" @selected((string) old('year_level', '1') === (string) $yearLevel)>{{ $yearLevel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="semester">Semester</label>
                            <select class="form-select form-select-lg" id="semester" name="semester" required>
                                @foreach (['First Semester', 'Second Semester', 'Summer'] as $semester)
                                    <option value="{{ $semester }}" @selected(old('semester', 'First Semester') === $semester)>{{ $semester }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1')>
                        <label class="form-check-label fw-semibold" for="is_active">Subject is enabled</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($programs->isEmpty())>Save Subject</button>
                </div>
            </form>
        </div>
    </div>

    @foreach ($subjectMappings as $mapping)
        @php
            $mappingIsActive = $mapping->subject?->is_active && $activeSemester === $mapping->semester;
        @endphp
        <div class="modal fade" id="viewSubjectModal{{ $mapping->subject_program_id }}" tabindex="-1" aria-labelledby="viewSubjectModalLabel{{ $mapping->subject_program_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="viewSubjectModalLabel{{ $mapping->subject_program_id }}">Subject Details</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <div class="col-sm-5">
                                <div class="subject-detail-label mb-1">Subject Code</div>
                                <div class="fw-bold" style="color: var(--psu-navy);">{{ $mapping->subject?->subject_code }}</div>
                            </div>
                            <div class="col-sm-7">
                                <div class="subject-detail-label mb-1">Status</div>
                                <span class="badge {{ $mappingIsActive ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $mappingIsActive ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="col-12">
                                <div class="subject-detail-label mb-1">Subject Name</div>
                                <div class="fw-semibold">{{ $mapping->subject?->subject_name }}</div>
                            </div>
                            <div class="col-12">
                                <div class="subject-detail-label mb-1">Program</div>
                                <div class="fw-semibold">{{ $mapping->program?->program_name ?? 'Not assigned' }}</div>
                                <div class="small text-secondary">{{ $mapping->program?->college?->college_name ?? 'No college' }}</div>
                            </div>
                            <div class="col-sm-5">
                                <div class="subject-detail-label mb-1">Year Level</div>
                                <div>{{ $mapping->year_level }}</div>
                            </div>
                            <div class="col-sm-7">
                                <div class="subject-detail-label mb-1">Semester</div>
                                <div>{{ $mapping->semester }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editSubjectModal{{ $mapping->subject_program_id }}" tabindex="-1" aria-labelledby="editSubjectModalLabel{{ $mapping->subject_program_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('super-admin.subjects.update', ['subjectProgram' => $mapping, 'program' => $selectedProgramId, 'year_level' => $selectedYearLevel, 'semester' => $selectedSemester]) }}" class="modal-content" method="POST">
                    @csrf
                    @method('PUT')
                    <input name="is_active" type="hidden" value="0">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="editSubjectModalLabel{{ $mapping->subject_program_id }}">Edit Subject</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="edit_program_id_{{ $mapping->subject_program_id }}">Program</label>
                            <select class="form-select" id="edit_program_id_{{ $mapping->subject_program_id }}" name="program_id" required>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->program_id }}" @selected($mapping->program_id === $program->program_id)>{{ $program->program_name }} - {{ $program->college?->college_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-uppercase small" for="edit_subject_code_{{ $mapping->subject_program_id }}">Subject Code</label>
                                <input class="form-control" id="edit_subject_code_{{ $mapping->subject_program_id }}" name="subject_code" required type="text" value="{{ $mapping->subject?->subject_code }}">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-bold text-uppercase small" for="edit_subject_name_{{ $mapping->subject_program_id }}">Subject Name</label>
                                <input class="form-control" id="edit_subject_name_{{ $mapping->subject_program_id }}" name="subject_name" required type="text" value="{{ $mapping->subject?->subject_name }}">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-uppercase small" for="edit_year_level_{{ $mapping->subject_program_id }}">Year Level</label>
                                <select class="form-select" id="edit_year_level_{{ $mapping->subject_program_id }}" name="year_level" required>
                                    @foreach ([1, 2, 3, 4] as $yearLevel)
                                        <option value="{{ $yearLevel }}" @selected((int) $mapping->year_level === $yearLevel)>{{ $yearLevel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-bold text-uppercase small" for="edit_semester_{{ $mapping->subject_program_id }}">Semester</label>
                                <select class="form-select" id="edit_semester_{{ $mapping->subject_program_id }}" name="semester" required>
                                    @foreach (['First Semester', 'Second Semester', 'Summer'] as $semester)
                                        <option value="{{ $semester }}" @selected($mapping->semester === $semester)>{{ $semester }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" id="edit_is_active_{{ $mapping->subject_program_id }}" name="is_active" type="checkbox" value="1" @checked($mapping->subject?->is_active)>
                            <label class="form-check-label fw-semibold" for="edit_is_active_{{ $mapping->subject_program_id }}">Subject is enabled</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                        <button class="btn btn-psu px-4" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="deleteSubjectModal{{ $mapping->subject_program_id }}" tabindex="-1" aria-labelledby="deleteSubjectModalLabel{{ $mapping->subject_program_id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <form action="{{ route('super-admin.subjects.destroy', ['subjectProgram' => $mapping, 'program' => $selectedProgramId, 'year_level' => $selectedYearLevel, 'semester' => $selectedSemester]) }}" class="modal-content" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="deleteSubjectModalLabel{{ $mapping->subject_program_id }}">Delete Subject</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">Remove <strong>{{ $mapping->subject?->subject_code }}</strong> from:</p>
                        <p class="fw-semibold mb-0">{{ $mapping->program?->program_name }}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-danger" type="submit">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
