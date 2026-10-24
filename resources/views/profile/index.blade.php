@extends('layouts.portal')

@section('title', 'Profile | AIssessment')
@section('header', 'Profile')

@php
    $photoUrl = $user->profilePhotoUrl();
@endphp

@push('styles')
    <x-page-style path="css/pages/profile/index.css" />
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="profile-card profile-header-card mb-4">
        <div class="profile-main-info">
            <div class="profile-photo-large" data-profile-photo-display data-profile-photo-alt="{{ $user->displayName() }} profile picture">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $user->displayName() }} profile picture">
                @else
                    {{ $profileInitials ?? 'U' }}
                @endif
            </div>
            <div>
                <h2 class="brand-text h1 profile-name mb-2">{{ $user->displayName() }}</h2>
                <span class="profile-role-pill">
                    <span class="material-symbols-outlined fs-6">verified_user</span>
                    {{ $profileMeta }}
                </span>
            </div>
        </div>

        <button class="btn btn-psu d-inline-flex align-items-center justify-content-center gap-2" data-bs-target="#editProfileModal" data-bs-toggle="modal" type="button">
            <span class="material-symbols-outlined fs-5">edit</span>
            Edit Profile
        </button>
    </section>

    @include('profile.profile-details')

    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 class="modal-title h4" id="editProfileModalLabel">Edit Profile</h3>
                    </div>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>

                <div class="modal-body d-grid gap-3">
                    @include('profile.profile-picture-form')
                    @include('profile.password-form')
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const editModal = document.getElementById('editProfileModal');

                    if (editModal) {
                        bootstrap.Modal.getOrCreateInstance(editModal).show();
                    }
                });
            </script>
        @endpush
    @endif
@endsection
