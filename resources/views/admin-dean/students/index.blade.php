@extends('layouts.portal')

@section('title', 'Students | AIssessment Admin/Dean')
@section('header', 'Students')

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

        .avatar,
        .empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
        }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            font-weight: 800;
            flex-shrink: 0;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
        }

        .students-table {
            width: 100%;
            min-width: 1120px;
            table-layout: fixed;
        }

        .students-table th,
        .students-table td {
            overflow-wrap: anywhere;
        }
    </style>
@endpush

@push('scripts')
    @include('admin-dean.students.student-page-code')
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('mail_warning'))
        <div class="alert alert-warning">{{ session('mail_warning') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if ($scopedCollege)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            You are viewing students under <strong>{{ $scopedCollege->college_name }}</strong>.
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
        <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createStudentModal" data-bs-toggle="modal" type="button" @disabled($programs->isEmpty())>
            <span class="material-symbols-outlined fs-5">person_add</span>
            Create Student
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Students List</h3>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 students-table compact-data-table mobile-card-table">
                <colgroup>
                    <col style="width: 27%;">
                    <col style="width: 32%;">
                    <col style="width: 22%;">
                    <col style="width: 9%;">
                    <col style="width: 10%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td class="mobile-primary-cell" data-label="Student">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar">{{ strtoupper(substr($student->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                    <div style="min-width: 0;">
                                        <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $student->user?->displayName() ?? 'Unnamed student' }}</p>
                                        <p class="small text-secondary mb-0">{{ $student->student_number ?? 'Not assigned' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Program">
                                @if ($student->program)
                                    <p class="fw-semibold mb-0">{{ $student->program->program_name }}</p>
                                    <p class="small text-secondary mb-0">{{ $student->program->college?->college_name }}</p>
                                @else
                                    <span class="text-secondary fst-italic">No program</span>
                                @endif
                            </td>
                            <td data-label="Email">{{ $student->user?->email ?? 'No email' }}</td>
                            <td data-label="Status">
                                <span class="badge {{ $student->user?->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ ucfirst($student->user?->status ?? 'inactive') }}
                                </span>
                            </td>
                            <td class="text-center" data-label="Actions">
                                @if ($student->user)
                                    @include('partials.account-row-actions', ['accountUser' => $student->user])
                                @else
                                    <span class="text-secondary fst-italic">No account</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5 mobile-empty-cell" colspan="5">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">groups</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No students found</h4>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $students->count() }} {{ $students->count() === 1 ? 'entry' : 'entries' }}</span>
        </div>
    </section>

    @include('partials.account-action-popups', [
        'accountUsers' => $students->pluck('user')->filter(),
        'accountRoutePrefix' => 'admin-dean',
        'accountMode' => 'student',
        'accountPrograms' => $programs,
    ])
    @include('admin-dean.students.create-student-form')
    @include('partials.student-account-import', [
        'studentImportPrograms' => $programs,
        'studentImportSampleRoute' => route('admin-dean.students.import.sample'),
        'studentImportPreviewRoute' => route('admin-dean.students.import.preview'),
        'studentImportConfirmRoute' => route('admin-dean.students.import.confirm'),
    ])
@endsection
