@extends('layouts.portal')

@section('title', 'Assessments | AIssessment Instructor')
@section('header', 'Assessments')

@push('styles')
    <style>
        .assessment-card,
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

        .assessment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
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
        <a class="btn btn-psu d-inline-flex align-items-center gap-2 {{ (! $instructorProfile || $handledSubjects->isEmpty()) ? 'disabled' : '' }}" href="{{ route('instructor.assessments.create') }}" aria-disabled="{{ (! $instructorProfile || $handledSubjects->isEmpty()) ? 'true' : 'false' }}">
            <span class="material-symbols-outlined fs-5">add</span>
            New Assessment
        </a>
    </div>

    <div class="table-switch-tabs">
        <a class="btn btn-outline-primary assessment-tab-button table-switch-button {{ $activeAssessmentTab === 'draft' ? 'active' : '' }} d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments', ['tab' => 'draft']) }}">
            <span class="material-symbols-outlined fs-5">inventory_2</span>
            Draft / Stored Assessments
            <span class="table-switch-count">{{ $assessments->count() }}</span>
        </a>
        <a class="btn btn-outline-primary assessment-tab-button table-switch-button {{ $activeAssessmentTab === 'published' ? 'active' : '' }} d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments', ['tab' => 'published']) }}">
            <span class="material-symbols-outlined fs-5">task_alt</span>
            Published Assessments
            <span class="table-switch-count">{{ $publishedAssessments->count() }}</span>
        </a>
    </div>

    @if ($activeAssessmentTab === 'draft')
    @if ($assessments->isNotEmpty())
        <div class="row g-4">
            @foreach ($assessments as $assessment)
                <div class="col-xl-6">
                    <section class="assessment-card h-100" id="assessmentCard{{ $assessment->assessment_id }}">
                        <div class="directory-header px-4 py-3 d-flex justify-content-between gap-3">
                            <div>
                                <h2 class="h4 mb-1">{{ $assessment->title }}</h2>
                                <p class="small text-white-50 mb-0">{{ $assessment->subject?->subject_code }} - {{ $assessment->subject?->subject_name }}</p>
                            </div>
                            <span class="badge text-bg-light rounded-1 align-self-start">{{ ucfirst($assessment->status) }}</span>
                        </div>

                        <div class="p-4">
                            <div class="assessment-meta mb-3">
                                <span class="badge text-bg-primary rounded-1">{{ $assessmentTypes[$assessment->type] ?? ucfirst($assessment->type) }}</span>
                                <span class="badge text-bg-light border rounded-1">{{ $assessment->items_count }} item{{ $assessment->items_count === 1 ? '' : 's' }}</span>
                                <span class="badge text-bg-light border rounded-1">{{ $assessment->class_assessments_count }} class{{ $assessment->class_assessments_count === 1 ? '' : 'es' }}</span>
                            </div>

                            @if ($assessment->description)
                                <p class="text-secondary">{{ $assessment->description }}</p>
                            @endif

                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.show', $assessment) }}">
                                    <span class="material-symbols-outlined fs-5">edit_square</span>
                                    Open Builder
                                </a>
                                <a class="btn btn-psu d-inline-flex align-items-center gap-2 {{ $assessment->items_count === 0 ? 'disabled' : '' }}" href="{{ route('instructor.assessments.publish.form', ['assessment_key' => $assessment->public_id]) }}" aria-disabled="{{ $assessment->items_count === 0 ? 'true' : 'false' }}">
                                    <span class="material-symbols-outlined fs-5">publish</span>
                                    Publish
                                </a>
                                <button class="btn btn-outline-danger d-inline-flex align-items-center gap-2" data-bs-target="#deleteAssessmentModal{{ $assessment->assessment_id }}" data-bs-toggle="modal" type="button">
                                    <span class="material-symbols-outlined fs-5">delete</span>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
    @else
        <section class="directory-card p-5 text-center">
            <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">assignment</span></div>
            <h2 class="h4" style="color: var(--psu-navy);">No assessments yet</h2>
            <a class="btn btn-psu d-inline-flex align-items-center gap-2 {{ (! $instructorProfile || $handledSubjects->isEmpty()) ? 'disabled' : '' }}" href="{{ route('instructor.assessments.create') }}" aria-disabled="{{ (! $instructorProfile || $handledSubjects->isEmpty()) ? 'true' : 'false' }}">
                <span class="material-symbols-outlined fs-5">add</span>
                Create First Assessment
            </a>
        </section>
    @endif
    @else
        @if ($publishedAssessments->isNotEmpty())
            <div class="row g-4">
                @foreach ($publishedAssessments as $classAssessment)
                    <div class="col-xl-6">
                        <section class="assessment-card h-100">
                            <div class="directory-header px-4 py-3 d-flex justify-content-between gap-3">
                                <div>
                                    <h2 class="h4 mb-1">{{ $classAssessment->assessment?->title ?? 'Untitled Assessment' }}</h2>
                                    <p class="small text-white-50 mb-0">{{ $classAssessment->assessment?->subject?->subject_code }} - {{ $classAssessment->class?->class_name }}</p>
                                </div>
                                <span class="badge {{ $classAssessment->display_status === 'completed' ? 'text-bg-success' : 'text-bg-warning' }} rounded-1 align-self-start">
                                    {{ ucfirst($classAssessment->display_status) }}
                                </span>
                            </div>

                            <div class="p-4">
                                <div class="assessment-meta mb-3">
                                    <span class="badge text-bg-primary rounded-1">{{ ucfirst($classAssessment->publish_status) }}</span>
                                    <span class="badge text-bg-light border rounded-1">{{ $classAssessment->attempt_limit }} attempt{{ $classAssessment->attempt_limit === 1 ? '' : 's' }}</span>
                                    <span class="badge text-bg-light border rounded-1">{{ $classAssessment->submitted_count }} submission{{ $classAssessment->submitted_count === 1 ? '' : 's' }}</span>
                                    @if ($classAssessment->due_at)
                                        <span class="badge text-bg-light border rounded-1">Due {{ $classAssessment->due_at->format('M d, Y h:i A') }}</span>
                                    @endif
                                </div>

                                <p class="text-secondary mb-3">
                                    Published to {{ $classAssessment->class?->class_name ?? 'class' }}
                                    @if ($classAssessment->class?->school_year)
                                        for {{ $classAssessment->class->school_year }}
                                    @endif
                                </p>

                                <div class="d-flex flex-wrap gap-2">
                                    @if ($classAssessment->display_status === 'completed')
                                        <a class="btn btn-psu d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.results', $classAssessment) }}">
                                            <span class="material-symbols-outlined fs-5">analytics</span>
                                            View Results
                                        </a>
                                    @else
                                        <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="{{ route('instructor.assessments.show', $classAssessment->assessment) }}">
                                            <span class="material-symbols-outlined fs-5">visibility</span>
                                            View Assessment
                                        </a>
                                    @endif
                                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('instructor.classes.show', $classAssessment->class) }}">
                                        <span class="material-symbols-outlined fs-5">school</span>
                                        View Class
                                    </a>
                                </div>
                            </div>
                        </section>
                    </div>
                @endforeach
            </div>
        @else
            <section class="directory-card p-5 text-center">
                <div class="empty-icon mb-3 mx-auto"><span class="material-symbols-outlined fs-2">task_alt</span></div>
                <h2 class="h4" style="color: var(--psu-navy);">No published assessments yet</h2>
                <a class="btn btn-psu d-inline-flex align-items-center gap-2 {{ (! $instructorProfile || $assessments->isEmpty()) ? 'disabled' : '' }}" href="{{ route('instructor.assessments.publish.form') }}" aria-disabled="{{ (! $instructorProfile || $assessments->isEmpty()) ? 'true' : 'false' }}">
                    <span class="material-symbols-outlined fs-5">publish</span>
                    Publish Assessment
                </a>
            </section>
        @endif
    @endif

    @include('instructor.assessments.delete-assessment-popups')
@endsection
