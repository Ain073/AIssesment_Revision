@extends('layouts.portal')

@section('title', 'Subjects | AIssessment Department Chair')
@section('header', 'Subjects')
@section('record-search-toolbar', 'true')

@push('scripts')
    @if (request('action') === 'create-subject')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('subjectModal');

                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endpush

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

    <div class="record-toolbar">
        @include('partials.record-search', ['inToolbar' => true])
        <button class="btn btn-psu d-flex align-items-center gap-2 record-toolbar-actions" data-bs-target="#subjectModal" data-bs-toggle="modal" type="button" @disabled(! $scopedDepartment)>
            <span class="material-symbols-outlined fs-5">add</span>
            Subject
        </button>
    </div>

    <div data-subjects-workspace>
        @include('department-chair.subjects.partials.workspace')
    </div>

    <div data-subjects-modals>
        @include('department-chair.subjects.partials.modals')
    </div>
@endsection
