@extends('layouts.portal')

@section('title', $class->class_name . ' | AIssessment Instructor')
@section('header', 'Class Details')

@push('styles')
    <style>
        .stat-card,
        .detail-card,
        .directory-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .hero-card,
        .directory-header,
        .modal-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .class-tabbar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--psu-line);
        }

        .class-tablink {
            padding: 0.65rem 0.2rem;
            color: var(--psu-muted);
            text-decoration: none;
            font-weight: 700;
            border-bottom: 2px solid transparent;
        }

        .class-tablink.active {
            color: var(--psu-navy);
            border-bottom-color: var(--psu-gold);
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

        .avatar,
        .empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy);
            font-weight: 700;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
        }

        .students-table {
            min-width: 980px;
        }

        .student-performance {
            min-width: 190px;
        }

        .student-performance-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .student-performance-track {
            flex: 1;
            min-width: 0;
            height: 8px;
            overflow: hidden;
            border-radius: 3px;
        }

        .student-performance-track.passed {
            background: #d1e7dd;
        }

        .student-performance-track.failed {
            background: #f8d7da;
        }

        .student-performance-fill {
            display: block;
            height: 100%;
            min-width: 4px;
        }

        .student-performance-fill.passed {
            background: #198754;
        }

        .student-performance-fill.failed {
            background: #dc3545;
        }

        .student-performance-percent {
            flex: 0 0 44px;
            font-size: 0.78rem;
            font-weight: 800;
            text-align: right;
        }

        .join-link-field,
        .join-code-field {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 0.9rem;
        }

        .join-code-field {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-align: center;
        }

        .student-import-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.5rem 0 1rem;
            color: var(--psu-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .student-import-divider::before,
        .student-import-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--psu-line);
        }

        .student-import-panel {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8faff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }
    </style>
@endpush

@push('scripts')
    @if ($activeTab === 'students' && $errors->has('student_number'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('addStudentModal');

                if (modalElement) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif

    @if ($activeTab === 'students' && $errors->has('student_file'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('addStudentModal');

                if (modalElement) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif

    @if ($activeTab === 'students' && $importPreview)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('importPreviewModal');

                if (modalElement) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endif

    <script>
        document.addEventListener('click', function (event) {
            const copyButton = event.target.closest('[data-copy-target]');

            if (! copyButton || ! navigator.clipboard) {
                return;
            }

            const input = document.getElementById(copyButton.dataset.copyTarget);

            if (! input) {
                return;
            }

            navigator.clipboard.writeText(input.value).then(() => {
                copyButton.querySelector('.copy-label').textContent = 'Copied';
            });
        });
    </script>
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.classes') }}" style="color: var(--psu-navy-2);">
                <span class="material-symbols-outlined align-middle fs-6">arrow_back</span>
                Back to Classes
            </a>
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">{{ $class->class_name }}</h1>
            <p class="text-secondary mb-0">
                {{ $class->subject?->subject_code ?? 'No subject' }}{{ $class->subject ? ' - ' . $class->subject->subject_name : '' }}
                @if ($class->archived_at)
                    <span class="badge text-bg-secondary rounded-1 ms-2">Archived</span>
                @endif
            </p>
        </div>

        @if ($activeTab === 'students' && ! $class->archived_at)
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" data-bs-target="#joinLinkModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">link</span>
                    Join Code
                </button>
                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#addStudentModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">person_add</span>
                    Add Student
                </button>
            </div>
        @endif
    </div>

    @if ($class->archived_at)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            This class is archived as a record. Restore it from the Archived Classes list before making changes.
        </div>
    @endif

    <section class="mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="stat-card p-4 h-100">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Students</p>
                    <div class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $class->students_count }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-4 h-100">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Join Requests</p>
                    <div class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $pendingJoinRequests->count() }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-4 h-100">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Published Assessments</p>
                    <div class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $class->class_assessments_count }}</div>
                </div>
            </div>
        </div>
    </section>

    <div class="class-tabbar mb-4">
        @foreach ($classTabs as $tab)
            <a class="class-tablink {{ $tab['active'] ? 'active' : '' }}" href="{{ $tab['href'] }}">{{ $tab['label'] }}</a>
        @endforeach
    </div>

    @if ($activeTab === 'overview')
        <div class="row g-4">
            <div class="col-lg-6">
                <section class="detail-card p-4 h-100">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Class Information</p>
                    <div class="d-grid gap-3">
                        <div>
                            <p class="small text-secondary mb-1">Subject</p>
                            <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $class->subject?->subject_code ?? 'Not assigned' }}</p>
                            <p class="text-secondary mb-0">{{ $class->subject?->subject_name ?? 'No subject selected yet' }}</p>
                        </div>
                        <div>
                            <p class="small text-secondary mb-1">Department</p>
                            <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $class->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</p>
                        </div>
                        <div>
                            <p class="small text-secondary mb-1">College</p>
                            <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $class->instructorProfile?->department?->college?->college_name ?? 'Not assigned' }}</p>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="detail-card p-4 h-100">
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Current Status</p>
                    <div class="d-grid gap-3">
                        <div>
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">Roster Setup</p>
                            <p class="text-secondary mb-0">There are currently {{ $class->students_count }} student{{ $class->students_count === 1 ? '' : 's' }} linked to this class.</p>
                        </div>
                        <div>
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">Assessment Setup</p>
                            <p class="text-secondary mb-0">{{ $class->class_assessments_count }} assessment{{ $class->class_assessments_count === 1 ? '' : 's' }} published to this class.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    @elseif ($activeTab === 'assessments')
        @if ($classAssessments->isNotEmpty())
            <div class="d-grid gap-3">
                @foreach ($classAssessments as $classAssessment)
                    <section class="detail-card p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div>
                                <h3 class="h5 fw-bold mb-1" style="color: var(--psu-navy);">{{ $classAssessment->assessment?->title ?? 'Untitled Assessment' }}</h3>
                                <p class="small text-secondary mb-0">
                                    {{ ucfirst($classAssessment->assessment?->type ?? 'assessment') }}
                                    @if ($classAssessment->due_at)
                                        | Due {{ $classAssessment->due_at->format('M d, Y h:i A') }}
                                    @endif
                                </p>
                            </div>
                            <span class="badge {{ $classAssessment->display_status === 'completed' ? 'text-bg-success' : 'text-bg-warning' }} rounded-1">
                                {{ ucfirst($classAssessment->display_status) }}
                            </span>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.show', $classAssessment->assessment) }}">
                                <span class="material-symbols-outlined fs-5">visibility</span>
                                View Assessment
                            </a>
                            @if ($classAssessment->display_status === 'completed')
                                <a class="btn btn-psu d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.results', $classAssessment) }}">
                                    <span class="material-symbols-outlined fs-5">analytics</span>
                                    View Results
                                </a>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            <section class="detail-card p-5 text-center">
                <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">assignment</span></div>
                <h3 class="h5 mb-2" style="color: var(--psu-navy);">No assessments published</h3>
                <a class="btn btn-psu d-inline-flex align-items-center gap-2 mt-2" href="{{ route('instructor.assessments.publish.form') }}">
                    <span class="material-symbols-outlined fs-5">publish</span>
                    Publish Assessment
                </a>
            </section>
        @endif
    @else
        @if (! $class->archived_at && $pendingJoinRequests->isNotEmpty())
            <section class="directory-card shadow-sm mb-4">
                <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                    <h3 class="h4 mb-0">Pending Join Requests</h3>
                    <span class="small text-white-50">Approve only students who belong in this class</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 students-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Program</th>
                                <th>Requested</th>
                                <th class="text-end">Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendingJoinRequests as $joinRequest)
                                @php($student = $joinRequest->studentProfile)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="avatar">{{ strtoupper(substr($student?->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                            <div>
                                                <div class="fw-bold" style="color: var(--psu-navy);">{{ $student?->user?->displayName() ?? 'Unknown student' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $student?->student_number ?? 'Unavailable' }}</td>
                                    <td>
                                        @if ($student?->program)
                                            <div class="fw-semibold">{{ $student->program->program_name }}</div>
                                            <div class="small text-secondary">{{ $student->program->college?->college_name }}</div>
                                        @else
                                            <span class="text-secondary">Not assigned</span>
                                        @endif
                                    </td>
                                    <td>{{ $joinRequest->requested_at?->format('M d, Y g:i A') ?? 'Recently' }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <form action="{{ route('instructor.classes.join-requests.approve', ['class' => $class, 'joinRequest' => $joinRequest]) }}" method="POST" data-ajax-form data-reload-page-on-success="true">
                                                @csrf
                                                <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" type="submit">
                                                    <span class="material-symbols-outlined fs-6">check</span>
                                                    Approve
                                                </button>
                                            </form>
                                            <form action="{{ route('instructor.classes.join-requests.reject', ['class' => $class, 'joinRequest' => $joinRequest]) }}" method="POST" onsubmit="return confirm('Reject this join request?');" data-ajax-form data-reload-page-on-success="true">
                                                @csrf
                                                <button class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" type="submit">
                                                    <span class="material-symbols-outlined fs-6">close</span>
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="directory-card shadow-sm">
            <div class="directory-header d-flex align-items-center justify-content-between px-4 py-3">
                <h3 class="h4 mb-0">Students in Class</h3>
                <span class="small text-white-50">Add manually, import a file, or approve join-link requests</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 students-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Performance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($enrolledStudents as $student)
                            @php($performance = $studentPerformance->get($student->student_profile_id))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar">{{ strtoupper(substr($student->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                        <div>
                                            <div class="fw-bold" style="color: var(--psu-navy);">{{ $student->user?->displayName() ?? 'Unnamed student' }}</div>
                                            <div class="small text-secondary">{{ $student->student_number ?? 'No student number' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($student->program)
                                        <div class="fw-semibold">{{ $student->program->program_name }}</div>
                                        <div class="small text-secondary">{{ $student->program->college?->college_name }}</div>
                                    @else
                                        <span class="text-secondary">Not assigned</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($performance['has_results'])
                                        <div class="student-performance">
                                            <div class="student-performance-row">
                                                <div
                                                    class="student-performance-track {{ $performance['passed'] ? 'passed' : 'failed' }}"
                                                    role="img"
                                                    aria-label="{{ $performance['percentage'] }} percent performance"
                                                >
                                                    <span
                                                        class="student-performance-fill {{ $performance['passed'] ? 'passed' : 'failed' }}"
                                                        style="width: {{ $performance['percentage'] }}%;"
                                                    ></span>
                                                </div>
                                                <span class="student-performance-percent {{ $performance['passed'] ? 'text-success' : 'text-danger' }}">
                                                    {{ $performance['percentage'] }}%
                                                </span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-secondary small">No results</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! $class->archived_at)
                                        <form action="{{ route('instructor.classes.students.destroy', ['class' => $class, 'studentProfile' => $student]) }}" method="POST" onsubmit="return confirm('Remove this student from the class?');" data-ajax-form data-reload-page-on-success="true">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" type="submit">
                                                <span class="material-symbols-outlined fs-6">delete</span>
                                                Remove
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-secondary small">Record only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center py-5" colspan="4">
                                    <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">groups</span></div>
                                    <h4 class="h4" style="color: var(--psu-navy);">No students enrolled yet</h4>
                                    <p class="text-secondary mb-4">Use a valid student number from an existing student account to add the first student to this class.</p>
                                    @if (! $class->archived_at)
                                        <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#addStudentModal" data-bs-toggle="modal" type="button">
                                            <span class="material-symbols-outlined fs-5">person_add</span>
                                            Add First Student
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex align-items-center justify-content-between px-4 py-3 border-top" style="background: #edf2ff;">
                <span class="small text-secondary">Showing {{ $enrolledStudents->count() }} {{ $enrolledStudents->count() === 1 ? 'student' : 'students' }}</span>
                <span class="small text-secondary">Only the class owner can manage this roster</span>
            </div>
        </section>

        @if (! $class->archived_at)
        <div class="modal fade" id="joinLinkModal" tabindex="-1" aria-labelledby="joinLinkModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="joinLinkModalLabel">Class Join Code</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label fw-bold text-uppercase small" for="classJoinCode">Share this code with students</label>
                        <div class="input-group mb-3">
                            <input class="form-control join-code-field" id="classJoinCode" readonly type="text" value="{{ $class->join_code }}">
                            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-1" data-copy-target="classJoinCode" type="button">
                                <span class="material-symbols-outlined fs-6">content_copy</span>
                                <span class="copy-label">Copy</span>
                            </button>
                        </div>

                        <label class="form-label fw-bold text-uppercase small" for="classJoinLink">Optional join link</label>
                        <div class="input-group">
                            <input class="form-control join-link-field" id="classJoinLink" readonly type="text" value="{{ $classJoinLink }}">
                            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-1" data-copy-target="classJoinLink" type="button">
                                <span class="material-symbols-outlined fs-6">content_copy</span>
                                <span class="copy-label">Copy</span>
                            </button>
                        </div>
                        <div class="alert alert-warning border-0 mt-3 mb-2">
                            Use the class code for now. The join link will only be final after the system is uploaded online.
                        </div>
                        <div class="alert alert-primary border-0 mb-0">
                            Students who use the code or link will only send a request. They will not be enrolled until you approve them.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="addStudentModalLabel">Add Student to Class</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('instructor.classes.students.store', $class) }}" method="POST" data-ajax-form data-reset-on-success="true" data-reload-page-on-success="true">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold text-uppercase small" for="student_number">Student Number</label>
                                <input class="form-control form-control-lg" id="student_number" name="student_number" placeholder="e.g. 2024-00001" required type="text" value="{{ old('student_number') }}">
                            </div>
                            <div class="d-flex justify-content-end">
                                <button class="btn btn-psu px-4" type="submit">Add Student</button>
                            </div>
                        </form>

                        <div class="student-import-divider">or</div>

                        <button
                            class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2"
                            data-bs-toggle="collapse"
                            data-bs-target="#studentImportPanel"
                            type="button"
                            aria-expanded="{{ $errors->has('student_file') ? 'true' : 'false' }}"
                            aria-controls="studentImportPanel"
                        >
                            <span class="material-symbols-outlined">upload_file</span>
                            Import Students
                        </button>

                        <div class="collapse {{ $errors->has('student_file') ? 'show' : '' }}" id="studentImportPanel">
                            <div class="student-import-panel">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div class="d-flex align-items-center gap-2 small">
                                        <span class="badge text-bg-light border">.txt</span>
                                        <span class="badge text-bg-light border">.csv</span>
                                        <span class="badge text-bg-light border">.xlsx</span>
                                    </div>
                                    <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.classes.students.import.sample', $class) }}" style="color: var(--psu-navy-2);">
                                        Download Sample CSV
                                    </a>
                                </div>

                                <form action="{{ route('instructor.classes.students.import.preview', $class) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-uppercase small" for="student_file">Roster File</label>
                                        <input class="form-control" id="student_file" name="student_file" type="file" accept=".csv,.txt,.xlsx" required>
                                        <div class="form-text">Maximum file size: 2 MB.</div>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button class="btn btn-psu d-inline-flex align-items-center gap-2 px-4" type="submit">
                                            <span class="material-symbols-outlined fs-6">preview</span>
                                            Preview Import
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if (! $class->archived_at && $importPreview)
            <div class="modal fade" id="importPreviewModal" tabindex="-1" aria-labelledby="importPreviewModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title h4" id="importPreviewModalLabel">Import Preview</h3>
                            <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="stat-card p-3 h-100">
                                        <p class="small fw-bold text-secondary text-uppercase mb-2">Rows Found</p>
                                        <div class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $importPreview['summary']['total'] ?? 0 }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-3 h-100">
                                        <p class="small fw-bold text-secondary text-uppercase mb-2">Ready</p>
                                        <div class="display-6 fw-bold text-success">{{ $importPreview['summary']['ready'] ?? 0 }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-3 h-100">
                                        <p class="small fw-bold text-secondary text-uppercase mb-2">Already Enrolled</p>
                                        <div class="display-6 fw-bold text-info">{{ $importPreview['summary']['already_enrolled'] ?? 0 }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card p-3 h-100">
                                        <p class="small fw-bold text-secondary text-uppercase mb-2">Needs Review</p>
                                        <div class="display-6 fw-bold text-warning">{{ ($importPreview['summary']['inactive'] ?? 0) + ($importPreview['summary']['not_found'] ?? 0) }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0 students-table">
                                    <thead>
                                        <tr>
                                            <th>Student Number</th>
                                            <th>Student</th>
                                            <th>Program</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($importPreview['rows'] as $row)
                                            <tr>
                                                <td>{{ $row['student_number'] }}</td>
                                                <td>{{ $row['student_name'] }}</td>
                                                <td>{{ $row['program_name'] }}</td>
                                                <td><span class="badge {{ $row['status_class'] }} rounded-1">{{ $row['status_badge'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                            <form action="{{ route('instructor.classes.students.import.confirm', $class) }}" method="POST">
                                @csrf
                                <input type="hidden" name="import_token" value="{{ $importPreview['token'] }}">
                                <button class="btn btn-psu px-4" type="submit" @disabled(($importPreview['summary']['ready'] ?? 0) === 0)>
                                    Confirm Import
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection
