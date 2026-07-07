@extends('layouts.portal')

@section('title', 'Profile | AIssessment')
@section('header', 'Profile')

@php
    $photoUrl = $user->profile_photo_path
        ? asset('storage/'.$user->profile_photo_path)
        : null;
@endphp

@push('styles')
    <style>
        .profile-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.06);
        }

        .profile-header-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            padding: 1.5rem;
        }

        .profile-main-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }

        .profile-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--psu-line);
        }

        .profile-card-body {
            padding: 1.5rem;
        }

        .profile-photo-large {
            width: 96px;
            height: 96px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex: 0 0 96px;
            border: 4px solid var(--psu-gold-soft);
            border-radius: 50%;
            background: var(--psu-gold);
            color: var(--psu-navy);
            font-size: 2rem;
            font-weight: 900;
        }

        .profile-photo-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            color: var(--psu-navy);
            line-height: 1.15;
        }

        .profile-role-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.7rem;
            border-radius: 999px;
            background: #edf2ff;
            color: var(--psu-navy);
            font-size: 0.82rem;
            font-weight: 800;
        }

        .profile-detail-list {
            display: grid;
            grid-template-columns: 1fr;
        }

        .profile-detail-item {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
            padding: 1rem 0;
            border-bottom: 1px solid #edf0f7;
        }

        .profile-detail-item:first-child {
            padding-top: 0;
        }

        .profile-detail-item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .profile-detail-label {
            color: var(--psu-muted);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin-bottom: 0;
        }

        .profile-detail-value {
            color: #111827;
            font-weight: 700;
            overflow-wrap: anywhere;
            margin-bottom: 0;
        }

        .profile-note {
            border-left: 4px solid var(--psu-gold);
            background: #fff9db;
            color: #5f4b00;
            padding: 0.85rem 1rem;
            border-radius: 0.35rem;
        }

        @media (max-width: 991.98px) {
            .profile-header-card {
                align-items: stretch;
                flex-direction: column;
            }

            .profile-main-info {
                align-items: center;
                flex-direction: column;
                text-align: center;
                width: 100%;
            }

            .profile-detail-list {
                grid-template-columns: 1fr;
            }

            .profile-detail-item {
                grid-template-columns: 1fr;
                gap: 0.2rem;
            }
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

    <section class="profile-card profile-header-card mb-4">
        <div class="profile-main-info">
            <div class="profile-photo-large">
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
