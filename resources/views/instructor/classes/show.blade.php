@extends('layouts.portal')

@section('title', $class->displayName() . ' | AIssessment Instructor')
@section('header', 'Class Details')

@push('styles')
    <x-page-style path="css/pages/instructor/classes/show.css" />
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

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
        <div class="flex-grow-1" style="min-width: 0;">
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
                @if ($class->semester)
                    <span class="mx-1">|</span> {{ $class->semester->semester_name }}
                @endif
                @if ($class->program)
                    <span class="mx-1">|</span> {{ $class->program->program_name }}
                @endif
                @if ($class->archived_at)
                    <span class="badge text-bg-secondary rounded-1 ms-2">Archived</span>
                @endif
            </p>
        </div>

        @if ($activeTab === 'students' && ! $class->archived_at)
            <div class="class-detail-actions d-flex flex-wrap justify-content-md-end gap-2 flex-shrink-0 ms-md-auto align-self-md-start">
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
                            <p class="small text-secondary mb-1">Program</p>
                            <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $class->program?->program_name ?? 'Not assigned' }}</p>
                        </div>
                        <div>
                            <p class="small text-secondary mb-1">Semester</p>
                            <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $class->semester?->semester_name ?? 'Not assigned' }}</p>
                        </div>
                        <div>
                            <p class="small text-secondary mb-1">Department</p>
                            <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $class->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</p>
                        </div>
                        <div>
                            <p class="small text-secondary mb-1">College</p>
                            <p class="fw-bold mb-0" style="color: var(--psu-navy);">{{ $class->instructorProfile?->department?->college?->shortName() ?? 'Not assigned' }}</p>
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
                    @php($submittedCount = $publishAssessment->submissions->count())
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
                            @elseif ($submittedCount > 0)
                                <a class="btn btn-psu d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.results', $publishAssessment) }}">
                                    <span class="material-symbols-outlined fs-5">fact_check</span>
                                    View Submissions
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
            @include('instructor.classes.remove-student-popup')
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
