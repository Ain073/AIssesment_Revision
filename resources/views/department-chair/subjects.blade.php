@extends('layouts.portal')

@section('title', 'Subjects | AIssessment Department Chair')
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

        .subjects-table {
            min-width: 980px;
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

        .modal-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
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
                <p class="small fw-bold text-secondary text-uppercase mb-2">Total Subjects</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalSubjects }}</span>
                    <span class="small text-secondary">Current subject records</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Active Subjects</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $activeSubjects }}</span>
                    <span class="small text-secondary">Currently available for use</span>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-primary border-0 shadow-sm mb-4">
        Subject creation is now centralized under the Super Admin. This page stays available so the Department Chair can review the shared subject catalog.
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Subjects List</h3>
            <span class="small text-white-50">Subject records managed under your department scope</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 subjects-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subject Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="icon-box"><span class="material-symbols-outlined">menu_book</span></span>
                                    <span class="fw-bold" style="color: var(--psu-navy);">{{ $subject->subject_code }}</span>
                                </div>
                            </td>
                            <td>{{ $subject->subject_name }}</td>
                            <td>
                                <span class="badge {{ $subject->is_active ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ $subject->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="3">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">menu_book</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No subjects yet</h4>
                                <p class="text-secondary mb-0">The shared subject catalog is still empty. Add the first subject from the Super Admin portal.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $subjects->count() }} {{ $subjects->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Subject catalog is centrally managed by Super Admin</span>
        </div>
    </section>
@endsection
