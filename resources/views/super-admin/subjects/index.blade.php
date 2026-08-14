@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $showTopbarSearch = true;
    $topbarSearchPlaceholder = 'Search subjects...';
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


@section('content')
    {{-- Page messages --}}
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- Active semester setting --}}
    <div class="academic-term-toolbar super-admin-form-toolbar d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
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

    {{-- Filters and actions --}}
    <div class="subject-toolbar super-admin-form-toolbar d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form action="{{ route('super-admin.subjects') }}" class="d-flex flex-wrap align-items-end gap-4" method="GET">
            <div>
                <label class="form-label small fw-bold text-uppercase mb-1" for="program-filter">View Program</label>
                <select class="form-select program-filter-select" id="program-filter" name="program" onchange="this.form.submit()">
                    <option value="">All Programs</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->public_id }}" @selected($selectedProgramKey === $program->public_id)>
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

    {{-- Subjects table --}}
    <section class="directory-card shadow-sm">
        <div class="directory-header px-4 py-3">
            <h3 class="h4 mb-0">Subjects by Program</h3>
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
                            <td class="fw-bold subject-primary-cell" data-label="Subject" style="color: var(--psu-navy);">
                                <div>{{ $mapping->subject?->subject_code }}</div>
                                <div class="small text-secondary fw-semibold mt-1 d-md-none">{{ $mapping->subject?->subject_name }}</div>
                            </td>
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
                                <button class="btn btn-sm record-action-trigger" data-bs-target="#viewSubjectModal{{ $mapping->subject_program_id }}" data-bs-toggle="modal" type="button" aria-label="View {{ $mapping->subject?->subject_name }}">
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5 subject-empty-cell" colspan="7">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">menu_book</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">
                                    {{ $hasSubjectFilters ? 'No subjects match these filters' : 'No subject mappings yet' }}
                                </h4>
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

        <div class="px-4 py-3 border-top" style="background: #eff4ff;">
            <span class="small text-secondary">Showing {{ $subjectMappings->count() }} {{ $subjectMappings->count() === 1 ? 'entry' : 'entries' }}</span>
        </div>
    </section>

    @include('super-admin.subjects.create-subject-form')
    @include('super-admin.subjects.view-edit-delete-subject-popups')
@endsection
