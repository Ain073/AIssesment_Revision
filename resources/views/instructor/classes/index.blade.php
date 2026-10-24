@extends('layouts.portal')

@section('title', 'Classes | AIssessment Instructor')
@section('header', 'Classes')
@section('record-search-toolbar', 'true')

@push('styles')
    <x-page-style path="css/pages/instructor/classes/index.css" />
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div data-table-tabs-root data-table-tabs-param="tab" data-table-tabs-default="{{ $activeClassTab }}" data-table-tabs-selected="{{ $activeClassTab }}">
        <div class="record-toolbar">
            @include('partials.record-search', ['inToolbar' => true])
            <div class="record-toolbar-actions">
                <button class="btn btn-psu d-flex align-items-center gap-2 {{ $activeClassTab === 'active' ? '' : 'd-none' }}" data-table-tab-panel="active" data-bs-target="#classModal" data-bs-toggle="modal" type="button" @disabled(! $instructorProfile || $subjects->isEmpty() || $activePrograms->isEmpty()) @if ($activeClassTab !== 'active') hidden @endif>
                <span class="material-symbols-outlined fs-5">add</span>
                Class
            </button>
            </div>
        </div>
        <div data-poll-url="{{ route('instructor.classes.live') }}" data-poll-interval="10000">
            @include('instructor.classes.class-list')
        </div>
    </div>

    @include('instructor.classes.create-class-form')
@endsection

@push('scripts')
    @if (request('action') === 'create-class')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('classModal');
                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endpush
