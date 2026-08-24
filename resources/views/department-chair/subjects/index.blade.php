@extends('layouts.portal')

@section('title', 'Subjects | AIssessment Department Chair')
@section('header', 'Subjects')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if (! $scopedDepartment)
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            This account has no assigned department yet, so subject scope cannot be resolved.
        </div>
    @endif

    <div data-subjects-term-toolbar>
        @include('department-chair.subjects.partials.term-toolbar')
    </div>

    <div data-subjects-workspace>
        @include('department-chair.subjects.partials.workspace')
    </div>

    <div data-subjects-modals>
        @include('department-chair.subjects.partials.modals')
    </div>
@endsection
