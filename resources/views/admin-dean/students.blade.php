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
            min-width: 1080px;
            table-layout: fixed;
        }

        .students-table th,
        .students-table td {
            overflow-wrap: anywhere;
        }
    </style>
@endpush

@push('scripts')
    @if ($errors->any() && old('base_role') === 'student')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('createStudentModal');

                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
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
            <span class="small text-white-50">Only student accounts within your scoped college are shown here</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 students-table">
                <colgroup>
                    <col style="width: 30%;">
                    <col style="width: 35%;">
                    <col style="width: 25%;">
                    <col style="width: 10%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar">{{ strtoupper(substr($student->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                    <div style="min-width: 0;">
                                        <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $student->user?->displayName() ?? 'Unnamed student' }}</p>
                                        <p class="small text-secondary mb-0">{{ $student->student_number ?? 'Not assigned' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($student->program)
                                    <p class="fw-semibold mb-0">{{ $student->program->program_name }}</p>
                                    <p class="small text-secondary mb-0">{{ $student->program->college?->college_name }}</p>
                                @else
                                    <span class="text-secondary fst-italic">No program</span>
                                @endif
                            </td>
                            <td>{{ $student->user?->email ?? 'No email' }}</td>
                            <td>
                                <span class="badge {{ $student->user?->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                    {{ ucfirst($student->user?->status ?? 'inactive') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5" colspan="4">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">groups</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No students found</h4>
                                <p class="text-secondary mb-0">No student accounts are currently mapped to this college scope.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $students->count() }} {{ $students->count() === 1 ? 'entry' : 'entries' }}</span>
            <span class="small text-secondary">Student creation is limited to your college programs</span>
        </div>
    </section>

    <div class="modal fade" id="createStudentModal" tabindex="-1" aria-labelledby="createStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form action="{{ route('admin-dean.users.store') }}" class="modal-content" method="POST">
                @csrf
                <input name="base_role" type="hidden" value="student">

                <div class="modal-header student-import-modal-header">
                    <h3 class="modal-title h4" id="createStudentModalLabel">Create Student Account</h3>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="student-create-mode" role="group" aria-label="Student account creation method">
                        <button class="btn active" type="button" aria-current="true">
                            <span class="material-symbols-outlined fs-5">person</span>
                            Manual Entry
                        </button>
                        <button class="btn" data-bs-target="#importStudentsModal" data-bs-toggle="modal" type="button">
                            <span class="material-symbols-outlined fs-5">upload_file</span>
                            Import File
                        </button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="student_first_name">First Name</label>
                            <input class="form-control" id="student_first_name" name="first_name" required type="text" value="{{ old('base_role') === 'student' ? old('first_name') : '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="student_middle_name">Middle Name</label>
                            <input class="form-control" id="student_middle_name" name="middle_name" type="text" value="{{ old('base_role') === 'student' ? old('middle_name') : '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-uppercase small" for="student_last_name">Last Name</label>
                            <input class="form-control" id="student_last_name" name="last_name" required type="text" value="{{ old('base_role') === 'student' ? old('last_name') : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="student_email">Email</label>
                            <input class="form-control" id="student_email" name="email" required type="email" value="{{ old('base_role') === 'student' ? old('email') : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="student_status">Status</label>
                            <select class="form-select" id="student_status" name="status" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="program_id">Program</label>
                            <select class="form-select" id="program_id" name="program_id" required>
                                <option value="">Select program</option>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->program_id }}" @selected((string) old('program_id') === (string) $program->program_id)>
                                        {{ $program->program_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="student_number">Student Number</label>
                            <input class="form-control" id="student_number" name="student_number" required type="text" value="{{ old('base_role') === 'student' ? old('student_number') : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="student_password">Password</label>
                            <input class="form-control" id="student_password" name="password" required type="password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-uppercase small" for="student_password_confirmation">Confirm Password</label>
                            <input class="form-control" id="student_password_confirmation" name="password_confirmation" required type="password">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-psu px-4" type="submit">Create Student</button>
                </div>
            </form>
        </div>
    </div>

    @include('partials.student-account-import', [
        'studentImportPrograms' => $programs,
        'studentImportSampleRoute' => route('admin-dean.students.import.sample'),
        'studentImportPreviewRoute' => route('admin-dean.students.import.preview'),
        'studentImportConfirmRoute' => route('admin-dean.students.import.confirm'),
    ])
@endsection
