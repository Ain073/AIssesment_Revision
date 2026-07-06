@extends('layouts.portal')

@section('title', 'Classes | AIssessment Instructor')
@section('header', 'Classes')

@push('styles')
    <style>
        .directory-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .directory-header,
        .modal-header {
            background: linear-gradient(90deg, var(--psu-navy) 0%, var(--psu-navy-2) 100%);
            color: #fff;
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

        .class-icon,
        .empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--psu-navy-2);
            background: var(--psu-gold-soft);
        }

        .class-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.25rem;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
        }

        .classes-table {
            min-width: 980px;
        }

        .join-code-pill {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            letter-spacing: 0.1em;
        }

        .btn-outline-psu {
            border-color: var(--psu-navy-2);
            color: var(--psu-navy-2);
        }

        .btn-outline-psu:hover,
        .btn-outline-psu:focus {
            background: var(--psu-navy-2);
            border-color: var(--psu-navy-2);
            color: #fff;
        }

        .action-icon-btn {
            width: 34px;
            height: 34px;
            padding: 0;
            border-radius: 0.25rem;
        }

        .class-list-tab {
            border-radius: 0.5rem;
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

    <div data-poll-url="{{ route('instructor.classes.live', ['tab' => $activeClassTab]) }}" data-poll-interval="5000">
        @include('instructor.classes.class-list')
    </div>

    @include('instructor.classes.create-class-form')
@endsection
