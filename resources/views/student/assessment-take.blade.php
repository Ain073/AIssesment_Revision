@extends('layouts.portal')

@section('title', $assessment->title . ' | AIssessment Student')
@section('header', 'Assessment Details')

@push('styles')
    <style>
        .assessment-panel,
        .detail-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .assessment-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .security-line {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            padding: 0.9rem 1rem;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #f8faff;
        }
    </style>
@endpush

@section('content')
    @php
        $enabledSecurities = collect([
            ['enabled' => $classAssessment->prevent_copy_paste, 'icon' => 'content_paste_off', 'label' => 'No copy / paste'],
            ['enabled' => $classAssessment->detect_tab_switch, 'icon' => 'tab', 'label' => 'Tab switch monitoring'],
            ['enabled' => $classAssessment->screenshot_protection, 'icon' => 'screenshot_monitor', 'label' => 'Screenshot deterrent'],
        ])->where('enabled');
    @endphp

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small fw-semibold text-decoration-none" href="{{ route('student.assessments') }}" style="color: var(--psu-navy-2);">
                <span class="material-symbols-outlined align-middle fs-6">arrow_back</span>
                Back to Assessments
            </a>
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">{{ $assessment->title }}</h1>
            <p class="text-secondary mb-0">{{ $class?->class_name }} - {{ $assessment->subject?->subject_code }}</p>
        </div>
        <span class="badge text-bg-primary rounded-1 px-3 py-2">{{ $assessment->items->count() }} question{{ $assessment->items->count() === 1 ? '' : 's' }}</span>
    </div>

    <section class="assessment-panel overflow-hidden mb-4">
        <div class="assessment-header px-4 py-3">
            <h2 class="h4 mb-0">Before You Start</h2>
        </div>
        <div class="p-4">
            @if ($assessment->instructions)
                <p class="mb-4">{{ $assessment->instructions }}</p>
            @else
                <p class="text-secondary mb-4">No special instructions were added by the instructor.</p>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="detail-card p-3 h-100">
                        <p class="small fw-bold text-secondary text-uppercase mb-1">Teacher</p>
                        <p class="fw-semibold mb-0" style="color: var(--psu-navy);">{{ $class?->instructorProfile?->user?->displayName() ?? 'Not assigned' }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="detail-card p-3 h-100">
                        <p class="small fw-bold text-secondary text-uppercase mb-1">Available</p>
                        <p class="fw-semibold mb-0" style="color: var(--psu-navy);">{{ $classAssessment->available_at?->format('M d, Y h:i A') ?? 'Now' }}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="detail-card p-3 h-100">
                        <p class="small fw-bold text-secondary text-uppercase mb-1">Due</p>
                        <p class="fw-semibold mb-0" style="color: var(--psu-navy);">{{ $classAssessment->due_at?->format('M d, Y h:i A') ?? 'No due date' }}</p>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="detail-card p-3 h-100">
                        <p class="small fw-bold text-secondary text-uppercase mb-1">Attempts</p>
                        <p class="fw-semibold mb-0" style="color: var(--psu-navy);">{{ $classAssessment->attempt_limit }} allowed</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="detail-card p-3 h-100">
                        <p class="small fw-bold text-secondary text-uppercase mb-1">Warnings</p>
                        <p class="fw-semibold mb-0" style="color: var(--psu-navy);">{{ $warningLimit }} allowed</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="detail-card p-3 h-100">
                        <p class="small fw-bold text-secondary text-uppercase mb-1">Items</p>
                        <p class="fw-semibold mb-0" style="color: var(--psu-navy);">{{ $assessment->items->count() }} total</p>
                    </div>
                </div>
            </div>

            <div class="security-line mb-4">
                <span class="material-symbols-outlined" style="color: var(--psu-navy-2);">verified_user</span>
                <div>
                    <p class="fw-bold mb-1" style="color: var(--psu-navy);">Monitoring starts after you press Start Assessment.</p>
                    <p class="text-secondary mb-2">Stay on the assessment page while answering. Restricted actions may count as warnings based on the instructor settings.</p>
                    @if ($enabledSecurities->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($enabledSecurities as $security)
                                <span class="badge text-bg-light border rounded-1 d-inline-flex align-items-center gap-1 px-2 py-2">
                                    <span class="material-symbols-outlined fs-6">{{ $security['icon'] }}</span>
                                    {{ $security['label'] }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <span class="badge text-bg-light border rounded-1 px-2 py-2">No extra security restrictions enabled</span>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-end gap-2">
                <a class="btn btn-outline-secondary px-4" href="{{ route('student.assessments') }}">Cancel</a>
                <a class="btn btn-psu px-4 d-inline-flex align-items-center gap-2 {{ $assessment->items->isEmpty() ? 'disabled' : '' }}" href="{{ route('student.assessments.start', $classAssessment) }}" aria-disabled="{{ $assessment->items->isEmpty() ? 'true' : 'false' }}">
                    <span class="material-symbols-outlined fs-5">play_arrow</span>
                    Start Assessment
                </a>
            </div>
        </div>
    </section>
@endsection
