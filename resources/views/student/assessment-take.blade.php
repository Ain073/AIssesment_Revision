@extends('layouts.portal')

@section('title', $assessment->title . ' | AIssessment Student')
@section('header', 'Take Assessment')

@push('styles')
    <style>
        .assessment-panel,
        .question-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .assessment-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .choice-line {
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            background: #f8faff;
        }
    </style>
@endpush

@section('content')
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
            <h2 class="h4 mb-0">Assessment Details</h2>
        </div>
        <div class="p-4">
            @if ($assessment->instructions)
                <p class="mb-3">{{ $assessment->instructions }}</p>
            @else
                <p class="text-secondary mb-3">No special instructions were added by the instructor.</p>
            @endif

            <div class="row g-3 small">
                <div class="col-md-4">
                    <span class="fw-bold text-secondary text-uppercase">Teacher:</span>
                    {{ $class?->instructorProfile?->user?->displayName() ?? 'Not assigned' }}
                </div>
                <div class="col-md-4">
                    <span class="fw-bold text-secondary text-uppercase">Available:</span>
                    {{ $classAssessment->available_at?->format('M d, Y h:i A') ?? 'Now' }}
                </div>
                <div class="col-md-4">
                    <span class="fw-bold text-secondary text-uppercase">Due:</span>
                    {{ $classAssessment->due_at?->format('M d, Y h:i A') ?? 'No due date' }}
                </div>
            </div>
        </div>
    </section>

    <form>
        <div class="d-grid gap-3">
            @foreach ($assessment->items as $item)
                <section class="question-card p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <h2 class="h5 mb-0" style="color: var(--psu-navy);">Question {{ $item->sort_order }}</h2>
                        <span class="badge text-bg-light border rounded-1">{{ $item->points }} point{{ (float) $item->points == 1.0 ? '' : 's' }}</span>
                    </div>

                    <p class="fw-semibold" style="color: var(--psu-navy);">{{ $item->question_text }}</p>

                    @if ($item->choices->isNotEmpty() && in_array($item->item_type, ['multiple_choice', 'true_false'], true))
                        <div class="d-grid gap-2">
                            @foreach ($item->choices as $choice)
                                <label class="choice-line d-flex align-items-center gap-2 mb-0">
                                    <input class="form-check-input mt-0" name="answers[{{ $item->assessment_item_id }}]" type="radio" value="{{ $choice->assessment_item_choice_id }}">
                                    <span>{{ $choice->choice_text }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif ($item->item_type === 'essay')
                        <textarea class="form-control" rows="5" placeholder="Type your answer"></textarea>
                    @else
                        <input class="form-control" type="text" placeholder="Type your answer">
                    @endif
                </section>
            @endforeach
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button class="btn btn-psu px-4 d-inline-flex align-items-center gap-2" type="button" disabled>
                <span class="material-symbols-outlined fs-5">send</span>
                Submit Assessment
            </button>
        </div>
    </form>
@endsection
