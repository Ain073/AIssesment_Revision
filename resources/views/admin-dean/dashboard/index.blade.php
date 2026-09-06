@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Dean')
@section('header', 'Dashboard')

@section('content')
    <div class="dashboard-shell">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <section class="dashboard-heading">
            <div>
                <div class="dashboard-eyebrow">Welcome back</div>
                <h2 class="dashboard-title">{{ $user->displayName() }}</h2>
                <p class="dashboard-subtitle">
                    {{ $scopedCollege?->college_name ?? 'College scope not assigned' }}
                </p>
            </div>

            @include('admin-dean.dashboard.partials.quick-actions')
        </section>

        <section class="dashboard-stat-grid" aria-label="College overview">
            @foreach ($stats as $stat)
                @include('partials.dashboard-stat-card', ['stat' => $stat])
            @endforeach
        </section>

        <div class="dashboard-summary-grid">
            @include('admin-dean.dashboard.partials.department-snapshot')

            @if ($teachingStats->isNotEmpty())
                @include('admin-dean.dashboard.partials.teaching-summary')
            @endif
        </div>
    </div>
@endsection
