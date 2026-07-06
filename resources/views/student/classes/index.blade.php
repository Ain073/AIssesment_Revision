@extends('layouts.portal')

@section('title', 'Classes | AIssessment Student')
@section('header', 'Classes')

@push('styles')
    <style>
        .class-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .directory-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--psu-gold-soft);
            color: var(--psu-navy);
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

        .join-code-input {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
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

    <div data-poll-url="{{ route('student.classes.live') }}" data-poll-interval="5000">
        @include('student.classes.class-list')
    </div>

    @include('student.classes.join-class-form')
    @include('student.classes.join-requests-popup')
@endsection

@push('scripts')
    @include('student.classes.class-page-code')
@endpush
