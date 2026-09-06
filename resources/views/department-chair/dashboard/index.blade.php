@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Department Chair')
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
                    {{ $scopedDepartment?->dept_name ?? 'Department scope not assigned' }}
                </p>
            </div>

            @include('department-chair.dashboard.partials.quick-actions')
        </section>

        <section class="dashboard-stat-grid" aria-label="Department overview">
            @foreach ($stats as $stat)
                @include('partials.dashboard-stat-card', ['stat' => $stat])
            @endforeach
        </section>

        <div class="dashboard-summary-grid">
            @include('department-chair.dashboard.partials.program-snapshot')

            @if ($teachingStats->isNotEmpty())
                @include('department-chair.dashboard.partials.teaching-summary')
            @endif
        </div>
    </div>
@endsection
