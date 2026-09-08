@extends('layouts.portal')

@section('title', 'Departments | AIssessment Dean')
@section('header', 'Departments')

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

    <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
        <button class="btn btn-psu d-flex align-items-center gap-2" data-bs-target="#departmentModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">add</span>
            Department
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
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 departments-table compact-data-table">
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
                                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#departmentModal" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-5">add</span>
                                    Department
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $departments->count() }} {{ $departments->count() === 1 ? 'entry' : 'entries' }}</span>
        </div>
    </section>

    @include('admin-dean.departments.create-department-form')
@endsection
