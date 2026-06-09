@extends('layouts.portal')

@section('title', 'Departments | AIssessment Admin/Dean')
@section('header', 'Departments')

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

        .departments-table {
            min-width: 880px;
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
                <p class="small fw-bold text-secondary text-uppercase mb-2">Total Departments</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalDepartments }}</span>
                    <span class="small text-secondary">Academic units listed</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card p-4">
                <p class="small fw-bold text-secondary text-uppercase mb-2">Total Colleges</p>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $totalColleges }}</span>
                    <span class="small text-secondary">Current available colleges</span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-lg-end gap-2 mb-4">
        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#departmentModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">add_business</span>
            Create Department
        </button>
    </div>

    @if ($scopedCollege)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            New departments created here will automatically belong to <strong>{{ $scopedCollege->college_name }}</strong>.
        </div>
    @endif

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Departments List</h3>
            <span class="small text-white-50">Department records currently visible in the dean workspace</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 departments-table">
                <thead>
                    <tr>
                        <th>Department Name</th>
                        <th>College</th>
                        <th>Instructors</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="icon-box"><span class="material-symbols-outlined">apartment</span></span>
                                    <span class="fw-bold" style="color: var(--psu-navy);">{{ $department->dept_name }}</span>
                                </div>
                            </td>
                            <td>{{ $department->college?->college_name ?? 'Not assigned' }}</td>
                            <td>
                                {{ $department->instructor_profiles_count }}
                                {{ $department->instructor_profiles_count === 1 ? 'Instructor' : 'Instructors' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="3">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">apartment</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No departments yet</h4>
                                <p class="text-secondary mb-4">Create the first department to start organizing academic units.</p>
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#departmentModal" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-5">add_business</span>
                                    Create First Department
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $departments->count() }} {{ $departments->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Programs and chair assignment can follow next</span>
        </div>
    </section>

    <div class="modal fade" id="departmentModal" tabindex="-1" aria-labelledby="departmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('admin-dean.departments.store') }}" class="modal-content" method="POST">
                @csrf
                <div class="modal-header">
                    <h3 class="modal-title h4" id="departmentModalLabel">New Department</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if ($scopedCollege)
                        <div class="mb-3">
                            <label class="form-label fw-bold text-uppercase small" for="college_scope_name">College</label>
                            <input class="form-control form-control-lg" id="college_scope_name" type="text" value="{{ $scopedCollege->college_name }}" readonly>
                            <input name="college_id" type="hidden" value="{{ $scopedCollege->college_id }}">
                        </div>
                    @else
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
                    @endif
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
