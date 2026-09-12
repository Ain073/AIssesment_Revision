@extends('layouts.portal')

@section('title', $class->displayName() . ' | AIssessment Instructor')
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
    @include('instructor.classes.class-show-page-code')
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
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">{{ $class->displayName() }}</h1>
            <p class="text-secondary mb-0">
                {{ $class->subject?->subject_code ?? 'No subject' }}{{ $class->subject ? ' - ' . $class->subject->subject_name : '' }}
                @if ($class->school_year)
                    <span class="mx-1">|</span> AY {{ $class->school_year }}
                @endif
                @if ($class->archived_at)
                    <span class="badge text-bg-secondary rounded-1 ms-2">Archived</span>
                @endif
            </p>
        </div>

        @if ($activeTab === 'students' && ! $class->archived_at)
            <div class="class-detail-actions d-flex flex-wrap justify-content-end gap-2">
                <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" data-bs-target="#joinLinkModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">link</span>
                    Join Code
                </button>
                <span data-poll-url="{{ route('instructor.classes.join-requests.live', ['class' => $class, 'part' => 'button']) }}" data-poll-interval="5000">
                    @include('instructor.classes.join-requests-button')
                </span>
                <button class="btn btn-psu d-inline-flex align-items-center gap-2" data-bs-target="#addStudentModal" data-bs-toggle="modal" type="button">
                    <span class="material-symbols-outlined fs-5">add</span>
                    Student
                </button>
            </div>
        @endif
    </div>

    @if ($class->archived_at)
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            This class is archived as a record. Restore it from the Archived Classes list before making changes.
        </div>
    @endif

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
                            <p class="text-secondary mb-0">{{ $class->publish_assessments_count }} assessment{{ $class->publish_assessments_count === 1 ? '' : 's' }} published to this class.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    @elseif ($activeTab === 'assessments')
        @if ($publishAssessments->isNotEmpty())
            <div class="d-grid gap-3">
                @foreach ($publishAssessments as $publishAssessment)
                    @php($publishedAssessment = $publishAssessment->assessment)
                    <section class="detail-card p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div>
                                <h3 class="h5 fw-bold mb-1" style="color: var(--psu-navy);">{{ $publishedAssessment?->title ?? 'Untitled Assessment' }}</h3>
                                <p class="small text-secondary mb-0">
                                    {{ ucfirst($publishedAssessment?->type ?? 'assessment') }}
                                    @if ($publishAssessment->due_at)
                                        | Due {{ $publishAssessment->due_at->format('M d, Y h:i A') }}
                                    @endif
                                </p>
                            </div>
                            <span class="badge {{ $publishAssessment->display_status === 'completed' ? 'text-bg-success' : 'text-bg-warning' }} rounded-1">
                                {{ ucfirst($publishAssessment->display_status) }}
                            </span>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @if ($publishedAssessment)
                                <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.show', $publishedAssessment) }}">
                                    <span class="material-symbols-outlined fs-5">visibility</span>
                                    View Assessment
                                </a>
                            @else
                                <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" type="button" disabled>
                                    <span class="material-symbols-outlined fs-5">visibility_off</span>
                                    View Assessment
                                </button>
                            @endif
                            @if ($publishAssessment->display_status === 'completed')
                                <a class="btn btn-psu d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.results', $publishAssessment) }}">
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
        <div data-poll-url="{{ route('instructor.classes.students.live', $class) }}" data-poll-interval="5000">
            @include('instructor.classes.student-table')
        </div>

        @if (! $class->archived_at)
            @include('instructor.classes.join-link-popup')
            <div class="modal fade" id="joinRequestsModal" tabindex="-1" aria-labelledby="joinRequestsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h3 class="modal-title h4 mb-1" id="joinRequestsModalLabel">Join Requests</h3>
                                <p class="small text-white-50 mb-0">{{ $class->displayName() }}{{ $class->join_code ? ' - ' . $class->join_code : '' }}</p>
                            </div>
                            <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0" data-poll-url="{{ route('instructor.classes.join-requests.live', ['class' => $class, 'part' => 'list']) }}" data-poll-interval="5000">
                            @include('instructor.classes.join-requests-list')
                        </div>
                    </div>
                </div>
            </div>
            @include('instructor.classes.add-student-form')
            @include('instructor.classes.import-preview-popup')
        @endif
    @endif
@endsection
