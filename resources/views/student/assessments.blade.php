@extends('layouts.portal')

@section('title', 'Assessments | AIssessment Student')
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

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="brand-text mb-1" style="color: var(--psu-navy);">My Assessments</h1>
            <p class="text-secondary mb-0">Published assessments from your enrolled classes appear here.</p>
        </div>
    </div>

    <div data-poll-url="{{ route('student.assessments.live') }}" data-poll-interval="5000">
        @include('student.partials.assessments-live')
    </div>
@endsection
