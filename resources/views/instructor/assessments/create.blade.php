@extends('layouts.portal')

@section('title', 'Create Assessment | AIssessment Instructor')
@section('header', 'Create Assessment')

@push('styles')
    <style>
        .builder-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .builder-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .step-track {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .step-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }

        .step-pill.active {
            border-color: var(--psu-navy-2);
            box-shadow: 0 12px 24px rgba(9, 39, 216, 0.12);
        }

        .step-number {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #edf2ff;
            color: var(--psu-navy);
            font-weight: 800;
            flex-shrink: 0;
        }

        .step-pill.active .step-number {
            background: var(--psu-navy-2);
            color: #fff;
        }

        @media (max-width: 991.98px) {
            .step-track {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4">
        <a class="small fw-semibold text-decoration-none" href="{{ route('instructor.assessments') }}" style="color: var(--psu-navy-2);">
            <span class="material-symbols-outlined align-middle fs-6">arrow_back</span>
            Back to Assessments
        </a>
        <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">Create Assessment</h1>
    </div>

    <section class="step-track mb-4">
        <div class="step-pill active">
            <span class="step-number">1</span>
            <div>
                <p class="fw-bold mb-0" style="color: var(--psu-navy);">Details and Instructions</p>
            </div>
        </div>
        <div class="step-pill">
            <span class="step-number">2</span>
            <div>
                <p class="fw-bold mb-0" style="color: var(--psu-navy);">Question Builder</p>
            </div>
        </div>
    </section>

    <section class="builder-card overflow-hidden">
        <div class="builder-header px-4 py-3">
            <h2 class="h4 mb-0">Assessment Details</h2>
        </div>
        <form action="{{ route('instructor.assessments.store') }}" method="POST" class="p-4">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold text-uppercase small" for="subject_id">Subject</label>
                    <select class="form-select form-select-lg" id="subject_id" name="subject_id" required>
                        <option value="">Select subject</option>
                        @foreach ($handledSubjects as $subject)
                            <option value="{{ $subject->subject_id }}" @selected(old('subject_id') == $subject->subject_id)>
                                {{ $subject->subject_code }} - {{ $subject->subject_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-uppercase small" for="type">Type</label>
                    <select class="form-select form-select-lg" id="type" name="type" required>
                        @foreach ($assessmentTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold text-uppercase small" for="title">Title</label>
                    <input class="form-control form-control-lg" id="title" name="title" required type="text" value="{{ old('title') }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold text-uppercase small" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-uppercase small" for="report_category">Report Category</label>
                    <select class="form-select" id="report_category" name="report_category" required>
                        <option value="">Select category</option>
                        @foreach ($reportCategories as $value => $label)
                            <option value="{{ $value }}" @selected(old('report_category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-uppercase small" for="reporting_term">Reporting Term</label>
                    <select class="form-select" id="reporting_term" name="reporting_term" required>
                        <option value="">Select term</option>
                        @foreach ($reportingTerms as $value => $label)
                            <option value="{{ $value }}" @selected(old('reporting_term') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold text-uppercase small" for="instructions">Instructions</label>
                    <textarea class="form-control" id="instructions" name="instructions" rows="4">{{ old('instructions') }}</textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a class="btn btn-outline-secondary px-4" href="{{ route('instructor.assessments') }}">Cancel</a>
                <button class="btn btn-psu px-4" type="submit">Save and Continue</button>
            </div>
        </form>
    </section>
@endsection
