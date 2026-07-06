@extends('layouts.portal')

@section('title', 'Teachers | AIssessment Admin/Dean')
@section('header', 'Teachers')

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
            text-align: center;
            padding: 1rem 0.75rem;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }

        .avatar {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
            border-radius: 50%;
            font-weight: 800;
            flex-shrink: 0;
        }

        .teachers-table {
            width: 100%;
            min-width: 1040px;
            table-layout: fixed;
        }

        .teachers-table th,
        .teachers-table td {
            overflow-wrap: anywhere;
        }

        .teacher-name {
            margin-bottom: 0;
            color: var(--psu-navy);
            line-height: 1.35;
            white-space: normal;
        }

        .teacher-subtext {
            white-space: normal;
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

    @if ($scopedCollege)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            You are viewing teachers under <strong>{{ $scopedCollege->college_name }}</strong>.
        </div>
    @endif

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createInstructorModal" data-bs-toggle="modal" type="button" @disabled($departments->isEmpty())>
            <span class="material-symbols-outlined fs-5">person_add</span>
            Create Instructor
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Teachers List</h3>
            <span class="small text-white-50">Only faculty accounts within your scoped college are shown here</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 teachers-table">
                <colgroup>
                    <col style="width: 25%;">
                    <col style="width: 22%;">
                    <col style="width: 25%;">
                    <col style="width: 18%;">
                    <col style="width: 10%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Authorization</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teachers as $teacher)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar">{{ strtoupper(substr($teacher->displayName(), 0, 1)) }}</span>
                                    <div>
                                        <p class="fw-bold teacher-name">{{ $teacher->displayName() }}</p>
                                        <p class="small text-secondary mb-0">{{ $teacher->instructorProfile?->employee_number ?? 'Not assigned' }}</p>
                                        @if ($teacher->name !== $teacher->displayName())
                                            <p class="small text-secondary mb-0 teacher-subtext">{{ $teacher->name }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $teacher->email }}</td>
                            <td>
                                @if ($teacher->instructorProfile?->department)
                                    <div>
                                        <p class="fw-semibold mb-0">{{ $teacher->instructorProfile->department->dept_name }}</p>
                                        <p class="small text-secondary mb-0">{{ $teacher->instructorProfile->department->college?->college_name }}</p>
                                    </div>
                                @else
                                    <span class="text-secondary fst-italic">Not assigned</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    @if ($teacher->hasRole('admin_dean'))
                                        <span class="badge text-bg-primary rounded-1">Admin/Dean</span>
                                    @endif
                                    @if ($teacher->hasRole('department_chair'))
                                        <span class="badge text-bg-info rounded-1">Department Chair</span>
                                    @endif
                                    @if (! $teacher->hasRole('admin_dean') && ! $teacher->hasRole('department_chair'))
                                        <span class="text-secondary fst-italic">Teacher only</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $teacher->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ ucfirst($teacher->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="5">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">badge</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No teachers found</h4>
                                <p class="text-secondary mb-0">No instructor accounts are currently mapped to this college scope.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $teachers->count() }} {{ $teachers->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Teacher creation is limited to your college departments</span>
        </div>
    </section>

    @include('admin-dean.teachers.create-instructor-form')
@endsection
