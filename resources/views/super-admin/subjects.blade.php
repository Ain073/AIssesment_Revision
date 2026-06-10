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

        .subjects-table {
            min-width: 1080px;
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
                <p class="small fw-bold text-secondary text-uppercase mb-2">Subject Catalog</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalSubjects }}</span>
                    <span class="small text-secondary">Unique subject records</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Program Mappings</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalSubjectMappings }}</span>
                    <span class="small text-secondary">Year and semester placements</span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
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

        <div class="table-responsive">
            <table class="table table-hover mb-0 subjects-table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Year Level</th>
                        <th>Semester</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjectMappings as $mapping)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $mapping->program?->program_name ?? 'Not assigned' }}</div>
                                <div class="small text-secondary">{{ $mapping->program?->college?->college_name ?? 'No college' }}</div>
                            </td>
                            <td class="fw-bold" style="color: var(--psu-navy);">{{ $mapping->subject?->subject_code }}</td>
                            <td>{{ $mapping->subject?->subject_name }}</td>
                            <td>{{ $mapping->year_level }}</td>
                            <td>{{ $mapping->semester }}</td>
                            <td>
                                <span class="badge {{ $mapping->subject?->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $mapping->subject?->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="6">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">menu_book</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No subject mappings yet</h4>
                                <p class="text-secondary mb-4">Create your first central subject record and map it to the right program slot.</p>
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
                                <option value="{{ $program->program_id }}" @selected(old('program_id') == $program->program_id)>{{ $program->program_name }} - {{ $program->college?->college_name }}</option>
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
                        <label class="form-check-label fw-semibold" for="is_active">Subject is active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    <button class="btn btn-psu px-4" type="submit" @disabled($programs->isEmpty())>Save Subject</button>
                </div>
            </form>
        </div>
    </div>
@endsection
