@extends('layouts.portal')

@section('title', 'Classes | AIssessment Student')
@section('header', 'Classes')
@section('record-search-toolbar', 'true')

@push('styles')
    <x-page-style path="css/pages/student/classes/index.css" />
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="record-toolbar">
        @include('partials.record-search', ['inToolbar' => true])
        <button class="btn btn-psu record-toolbar-actions d-inline-flex align-items-center gap-2" data-bs-target="#joinClassModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">login</span>
            Join Class
        </button>
    </div>

    <div data-poll-url="{{ route('student.classes.live') }}" data-poll-interval="5000">
        @include('student.classes.class-list')
    </div>

    @include('student.classes.join-class-form')
    @include('student.classes.join-requests-popup')
@endsection

@push('scripts')
    @include('student.classes.class-page-code')
@endpush
