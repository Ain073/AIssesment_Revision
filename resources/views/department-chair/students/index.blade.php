@extends('layouts.portal')

@section('title', 'Users | AIssessment Department Chair')
@section('header', 'Users')

@push('styles')
    <x-page-style path="css/pages/department-chair/students/index.css" />
@endpush

@push('scripts')
    @include('department-chair.students.student-page-code')
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

    @if (! $scopedDepartment)
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            This account has no assigned department yet, so student scope cannot be resolved.
        </div>
    @endif

    @include('partials.user-directory-tabs', [
        'userDirectoryTabs' => [
            [
                'label' => 'Teachers',
                'icon' => 'badge',
                'href' => route('department-chair.teachers'),
                'count' => $teachersCount,
                'active' => request()->routeIs('department-chair.teachers'),
            ],
            [
                'label' => 'Students',
                'icon' => 'groups',
                'href' => route('department-chair.students'),
                'count' => $students->count(),
                'active' => request()->routeIs('department-chair.students*'),
            ],
        ],
    ])

    <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
        <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createStudentModal" data-bs-toggle="modal" type="button" @disabled($scopedPrograms->isEmpty())>
            <span class="material-symbols-outlined fs-5">add</span>
            Student
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Students</h3>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 students-table compact-data-table mobile-card-table">
                <colgroup>
                    <col style="width: 28%;">
                    <col style="width: 34%;">
                    <col style="width: 27%;">
                    <col style="width: 11%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Email</th>
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
                                    <p class="small text-secondary mb-0">
                                        {{ collect([$student->program->department?->dept_name, $student->program->department?->college?->shortName()])->filter()->join(' - ') }}
                                    </p>
                                @else
                                    <span class="text-secondary fst-italic">No program</span>
                                @endif
                            </td>
                            <td data-label="Email">{{ $student->user?->email ?? 'No email' }}</td>
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
                            <td class="text-center py-5 mobile-empty-cell" colspan="4">
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
        'accountRoutePrefix' => 'department-chair',
        'accountMode' => 'student',
        'accountPrograms' => $scopedPrograms,
    ])
    @include('department-chair.students.create-student-form')
    @include('partials.student-account-import', [
        'studentImportPrograms' => $scopedPrograms,
        'studentImportSampleRoute' => route('department-chair.students.import.sample'),
        'studentImportPreviewRoute' => route('department-chair.students.import.preview'),
        'studentImportReviewRoute' => route('department-chair.students.import.review'),
        'studentImportConfirmRoute' => route('department-chair.students.import.confirm'),
    ])
@endsection
