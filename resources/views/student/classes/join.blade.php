@extends('layouts.portal')

@section('title', 'Join Class | AIssessment Student')
@section('header', 'Join Class')

@push('styles')
    <style>
        .join-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .join-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 1rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid var(--psu-line);
        }

        .detail-row:last-child {
            border-bottom: 0;
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

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <section class="join-card overflow-hidden">
                <div class="join-header px-4 py-4">
                    <p class="small text-white-50 text-uppercase fw-bold mb-2">Class Join Request</p>
                    <h1 class="brand-text h2 mb-1">{{ $class->class_name }}</h1>
                    <p class="mb-0 text-white-50">{{ $class->subject?->subject_code ?? 'No subject' }}{{ $class->subject ? ' - ' . $class->subject->subject_name : '' }}</p>
                </div>

                <div class="p-4">
                    <div class="mb-4">
                        <div class="detail-row">
                            <span class="small fw-bold text-secondary text-uppercase">Teacher</span>
                            <span>{{ $class->instructorProfile?->user?->displayName() ?? 'Not assigned' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="small fw-bold text-secondary text-uppercase">Department</span>
                            <span>{{ $class->instructorProfile?->department?->dept_name ?? 'Not assigned' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="small fw-bold text-secondary text-uppercase">School Year</span>
                            <span>{{ $class->school_year }}</span>
                        </div>
                    </div>

                    @if ($alreadyEnrolled)
                        <div class="alert alert-success border-0">
                            You are already enrolled in this class.
                        </div>
                        <a class="btn btn-psu portal-ajax-link px-4" href="{{ route('student.classes') }}">Back to Classes</a>
                    @elseif ($existingRequest?->status === 'pending')
                        <div class="alert alert-warning border-0">
                            Your join request is already pending. Please wait for your teacher to approve it.
                        </div>
                        <a class="btn btn-psu portal-ajax-link px-4" href="{{ route('student.classes') }}">Back to Classes</a>
                    @elseif ($existingRequest?->status === 'approved')
                        <div class="alert alert-success border-0">
                            Your request was approved. This class should now appear in your Classes page.
                        </div>
                        <a class="btn btn-psu portal-ajax-link px-4" href="{{ route('student.classes') }}">Back to Classes</a>
                    @else
                        @if ($existingRequest?->status === 'rejected')
                            <div class="alert alert-danger border-0">
                                Your previous request was rejected. You can send another request if your teacher asked you to try again.
                            </div>
                        @else
                            <div class="alert alert-primary border-0">
                                This will send a request to your teacher. You will not be enrolled until the teacher approves it.
                            </div>
                        @endif

                        <form action="{{ route('student.classes.join.request', $class->join_token) }}" method="POST" data-ajax-form data-redirect-on-success="{{ route('student.classes') }}">
                            @csrf
                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                <a class="btn btn-outline-secondary portal-ajax-link px-4" href="{{ route('student.classes') }}">Cancel</a>
                                <button class="btn btn-psu px-4" type="submit">Request to Join</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
