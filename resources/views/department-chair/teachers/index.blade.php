@extends('layouts.portal')

@section('title', 'Users | AIssessment Department Chair')
@section('header', 'Users')

@push('styles')
    <x-page-style path="css/pages/department-chair/teachers/index.css" />
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

    @include('partials.user-directory-tabs', [
        'userDirectoryTabs' => [
            [
                'label' => 'Teachers',
                'icon' => 'badge',
                'href' => route('department-chair.teachers'),
                'count' => $teachers->count(),
                'active' => request()->routeIs('department-chair.teachers'),
            ],
            [
                'label' => 'Students',
                'icon' => 'groups',
                'href' => route('department-chair.students'),
                'count' => $studentsCount,
                'active' => request()->routeIs('department-chair.students*'),
            ],
        ],
    ])

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#createInstructorModal" data-bs-toggle="modal" type="button" @disabled(! $scopedDepartment)>
            <span class="material-symbols-outlined fs-5">add</span>
            Instructor
        </button>
    </div>

    <section class="directory-card shadow-sm">
        <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
            <h3 class="h4 mb-0">Teachers</h3>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 teachers-table compact-data-table mobile-card-table">
                <colgroup>
                    <col style="width: 24%;">
                    <col style="width: 23%;">
                    <col style="width: 24%;">
                    <col style="width: 18%;">
                    <col style="width: 11%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teachers as $teacher)
                        <tr>
                            <td class="mobile-primary-cell" data-label="User">
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
                            <td data-label="Email">{{ $teacher->email }}</td>
                            <td data-label="Department">
                                @if ($teacher->instructorProfile?->department)
                                    <div>
                                        <p class="fw-semibold mb-0">{{ $teacher->instructorProfile->department->dept_name }}</p>
                                        <p class="small text-secondary mb-0">{{ $teacher->instructorProfile->department->college?->shortName() }}</p>
                                    </div>
                                @else
                                    <span class="text-secondary fst-italic">Not assigned</span>
                                @endif
                            </td>
                            <td data-label="Designation">
                                <div class="d-flex flex-wrap gap-2">
                                    @if ($teacher->hasRole('admin_dean'))
                                        <span class="badge text-bg-light border text-secondary rounded-1 fw-medium">Dean</span>
                                    @endif
                                    @if ($teacher->hasRole('department_chair'))
                                        <span class="badge text-bg-light border text-secondary rounded-1 fw-medium">Department Chair</span>
                                    @endif
                                    @if (! $teacher->hasRole('admin_dean') && ! $teacher->hasRole('department_chair'))
                                        <span class="text-secondary fst-italic">Teacher only</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center" data-label="Actions">
                                @include('partials.account-row-actions', ['accountUser' => $teacher])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center py-5 mobile-empty-cell" colspan="5">
                                <div class="empty-icon mb-3"><span class="material-symbols-outlined fs-2">badge</span></div>
                                <h4 class="h4" style="color: var(--psu-navy);">No teachers found</h4>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
            <span class="small text-secondary">Showing {{ $teachers->count() }} {{ $teachers->count() === 1 ? 'entry' : 'entries' }}</span>
        </div>
    </section>

    @include('partials.account-action-popups', [
        'accountUsers' => $teachers,
        'accountRoutePrefix' => 'department-chair',
        'accountMode' => 'instructor',
        'accountFixedDepartment' => $scopedDepartment,
    ])
    @include('department-chair.teachers.create-instructor-form')
@endsection
