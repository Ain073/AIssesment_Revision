@extends('layouts.portal')

@php
    $portalSubtitle = 'Super Admin Panel';
    $profileInitials = 'SA';
    $profileName = 'Super Admin';
    $profileMeta = 'System Controller';
    $showTopbarSearch = true;
    $topbarSearchPlaceholder = 'Search programs...';
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


@section('content')
    {{-- Page messages --}}
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- Filters and actions --}}
    <div class="program-toolbar super-admin-toolbar d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <form action="{{ route('super-admin.programs') }}" method="GET">
            <label class="form-label small fw-bold text-uppercase mb-1" for="college-filter">View College</label>
            <select class="form-select college-filter-select" id="college-filter" name="college" onchange="this.form.submit()">
                <option value="">All Colleges</option>
                @foreach ($colleges as $college)
                    <option value="{{ $college->public_id }}" @selected($selectedCollegeKey === $college->public_id)>
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

    {{-- Programs table --}}
    <section class="directory-card shadow-sm">
        <div class="directory-header px-4 py-3">
            <h3 class="h4 mb-0">Programs List</h3>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 programs-table mobile-card-table">
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
                            <td class="fw-bold mobile-primary-cell" data-label="Program" style="color: var(--psu-navy);">{{ $program->program_name }}</td>
                            <td data-label="College">{{ $program->college?->college_name ?? 'Not assigned' }}</td>
                            <td class="count-cell" data-label="Students">{{ $program->student_profiles_count }}</td>
                            <td data-label="Status">
                                <span class="badge {{ $program->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $program->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-center" data-label="Actions">
                                <button class="btn btn-sm record-action-trigger" data-bs-target="#viewProgramModal{{ $program->program_id }}" data-bs-toggle="modal" type="button" aria-label="View {{ $program->program_name }}">
                                    <span class="material-symbols-outlined fs-6">visibility</span>
                                                View
                                            </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5 mobile-empty-cell" colspan="5">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">school</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No programs yet</h4>
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
        </div>
    </section>

    @include('super-admin.programs.create-program-form')
    @include('super-admin.programs.view-edit-delete-program-popups')
@endsection
