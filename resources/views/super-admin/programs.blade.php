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

    <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#programModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">add</span>
            Add Program
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Programs List</h3>
            <span class="small text-white-50">Managed separately from colleges and subjects</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 programs-table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>College</th>
                        <th>Students</th>
                        <th>Subjects</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                        <tr>
                            <td class="fw-bold" style="color: var(--psu-navy);">{{ $program->program_name }}</td>
                            <td>{{ $program->college?->college_name ?? 'Not assigned' }}</td>
                            <td>{{ $program->student_profiles_count }}</td>
                            <td>{{ $program->subject_programs_count }}</td>
                            <td>
                                <span class="badge {{ $program->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $program->is_active ? 'Active' : 'Inactive' }}
                                </span>
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
@endsection
