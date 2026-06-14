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
            </p>
        </div>

        @if ($activeTab === 'students')
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
                    <p class="small fw-bold text-secondary text-uppercase mb-2">Assessments Taken</p>
                    <div class="display-6 fw-bold" style="color: var(--psu-navy);">{{ $assessmentsTakenCount }}</div>
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
                            <p class="text-secondary mb-0">Assessment creation will be attached here after we finish the roster flow.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    @elseif ($activeTab === 'assessments')
        <section class="detail-card p-5 text-center">
            <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">assignment</span></div>
            <h3 class="brand-text h2 mb-2" style="color: var(--psu-navy);">Assessments Come Next</h3>
            <p class="text-secondary mb-0">We kept this tab ready, but the roster flow comes first so assessment access stays organized and secure.</p>
        </section>
    @else
        @if ($pendingJoinRequests->isNotEmpty())
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
                                                <div class="small text-secondary">{{ $student?->user?->email ?? 'No email' }}</div>
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
                                            <form action="{{ route('instructor.classes.join-requests.approve', ['class' => $class, 'joinRequest' => $joinRequest]) }}" method="POST">
                                                @csrf
                                                <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-1" type="submit">
                                                    <span class="material-symbols-outlined fs-6">check</span>
                                                    Approve
                                                </button>
                                            </form>
                                            <form action="{{ route('instructor.classes.join-requests.reject', ['class' => $class, 'joinRequest' => $joinRequest]) }}" method="POST" onsubmit="return confirm('Reject this join request?');">
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
                            <th>Student Number</th>
                            <th>Program</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($enrolledStudents as $student)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar">{{ strtoupper(substr($student->user?->displayName() ?? 'S', 0, 1)) }}</span>
                                        <div>
                                            <div class="fw-bold" style="color: var(--psu-navy);">{{ $student->user?->displayName() ?? 'Unnamed student' }}</div>
                                            <div class="small text-secondary">{{ $student->user?->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $student->student_number }}</td>
                                <td>
                                    @if ($student->program)
                                        <div class="fw-semibold">{{ $student->program->program_name }}</div>
                                        <div class="small text-secondary">{{ $student->program->college?->college_name }}</div>
                                    @else
                                        <span class="text-secondary">Not assigned</span>
                                    @endif
                                </td>
                                <td>{{ $student->user?->email ?? 'No email' }}</td>
                                <td>
                                    <span class="badge {{ ($student->user?->status === 'active') ? 'text-bg-success' : 'text-bg-secondary' }} rounded-1">
                                        {{ ucfirst($student->user?->status ?? 'inactive') }}
                                    </span>
                                </td>
                                <td>
                                    <form action="{{ route('instructor.classes.students.destroy', ['class' => $class, 'studentProfile' => $student]) }}" method="POST" onsubmit="return confirm('Remove this student from the class?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" type="submit">
                                            <span class="material-symbols-outlined fs-6">delete</span>
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center py-5" colspan="6">
                                    <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">groups</span></div>
                                    <h4 class="h4" style="color: var(--psu-navy);">No students enrolled yet</h4>
                                    <p class="text-secondary mb-4">Use a valid student number from an existing student account to add the first student to this class.</p>
                                    <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#addStudentModal" data-bs-toggle="modal" type="button">
                                        <span class="material-symbols-outlined fs-5">person_add</span>
                                        Add First Student
                                    </button>
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
                        <form action="{{ route('instructor.classes.students.store', $class) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold text-uppercase small" for="student_number">Student Number</label>
                                <input class="form-control form-control-lg" id="student_number" name="student_number" placeholder="e.g. 2024-00001" required type="text" value="{{ old('student_number') }}">
                                <div class="form-text">This only accepts existing student accounts already created by the Super Admin.</div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button class="btn btn-psu px-4" type="submit">Add Student</button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="mb-3">
                            <p class="small fw-bold text-secondary text-uppercase mb-2">Or Import a File</p>
                            <div class="alert alert-primary border-0 mb-3">
                                Use one student number per line, or upload a file with a `student_number` column. The system will preview the records first before any student is added.
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2 small">
                                <span class="text-secondary">Accepted formats:</span>
                                <span class="badge text-bg-light border">.txt</span>
                                <span class="badge text-bg-light border">.csv</span>
                                <span class="badge text-bg-light border">.xlsx</span>
                                <a class="fw-semibold text-decoration-none ms-sm-2" href="{{ route('instructor.classes.students.import.sample', $class) }}" style="color: var(--psu-navy-2);">
                                    Download Sample CSV
                                </a>
                            </div>
                        </div>
                        <form action="{{ route('instructor.classes.students.import.preview', $class) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-bold text-uppercase small" for="student_file">Roster File</label>
                                <input class="form-control form-control-lg" id="student_file" name="student_file" type="file" accept=".csv,.txt,.xlsx" required>
                                <div class="form-text">Accepted formats: `.csv`, `.txt`, or `.xlsx`, up to 2 MB.</div>
                            </div>
                            <div class="mb-3">
                                <div class="small text-secondary">
                                    For Excel files, keep the student numbers in the first sheet. Best practice is one `student_number` column only.
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button class="btn btn-outline-primary px-4" type="submit">Read File</button>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Discard</button>
                    </div>
                </div>
            </div>
        </div>

        @if ($importPreview)
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
