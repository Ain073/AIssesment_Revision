@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Student')
@section('header', 'Student Dashboard')

@push('styles')
    <x-page-style path="css/pages/student/dashboard/index.css" />
@endpush

@section('content')
    @if (session('status'))
        <div class="alert alert-success flash-alert">{{ session('status') }}</div>
    @endif

    <section class="hero-card">
        <div class="hero-layout">
            <div>
                <p class="small fw-bold text-uppercase mb-2 hero-eyebrow">Welcome Back</p>
                <h2 class="brand-text hero-title">{{ $user->displayName() }}</h2>
            </div>

            <div class="hero-actions" aria-label="Quick actions">
                @foreach ($quickActions as $action)
                    <a class="hero-action-link" href="{{ $action['href'] }}">
                        <span class="hero-action-icon"><span class="material-symbols-outlined">{{ $action['icon'] }}</span></span>
                        <span>
                            <span class="fw-bold d-block">{{ $action['label'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="stat-grid">
        @foreach ($stats as $stat)
            <article class="stat-card">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <p class="stat-label">{{ $stat['label'] }}</p>
                        <div class="stat-value">{{ $stat['value'] }}</div>
                    </div>
                    <span class="icon-tile"><span class="material-symbols-outlined">{{ $stat['icon'] }}</span></span>
                </div>
            </article>
        @endforeach
    </section>

    <section class="mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="brand-text h4 section-title mb-1">Class Performance</h3>
            </div>
            <span class="badge text-bg-light border">{{ $classPerformance->count() }} active</span>
        </div>

        @if ($classPerformance->isNotEmpty())
            <div class="performance-grid">
                @foreach ($classPerformance as $performance)
                    @php
                        $percentage = $performance['average_percentage'];
                        $hasScore = $percentage !== null;
                        $passingPercentage = $performance['passing_percentage'] ?? 50;
                        $barColor = ! $hasScore
                            ? '#94a3b8'
                            : ($percentage >= $passingPercentage ? '#198754' : '#dc3545');
                    @endphp
                    <article class="performance-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h4 class="performance-heading">{{ $performance['class']->class_name }}</h4>
                                <p class="small text-secondary mb-0">
                                    {{ $performance['subject']?->subject_code ?? 'No subject' }}
                                    @if ($performance['instructor'])
                                        <span class="mx-1">-</span>
                                        {{ $performance['instructor']->displayName() }}
                                    @endif
                                </p>
                            </div>
                            <div class="text-end">
                                <div class="performance-percent" style="color: {{ $barColor }};">
                                    {{ $hasScore ? $percentage.'%' : 'N/A' }}
                                </div>
                            </div>
                        </div>

                        <div class="performance-track mb-3" aria-hidden="true">
                            <div class="performance-fill" style="width: {{ $hasScore ? min($percentage, 100) : 0 }}%; background: {{ $barColor }};"></div>
                        </div>

                        <div class="performance-stats">
                            <div class="performance-stat">
                                <p class="performance-stat-label">Released</p>
                                <p class="performance-stat-value">{{ $performance['released_count'] }} / {{ $performance['assigned_count'] }}</p>
                            </div>
                            <div class="performance-stat">
                                <p class="performance-stat-label">Passed</p>
                                <p class="performance-stat-value text-success">{{ $performance['passed_count'] }}</p>
                            </div>
                            <div class="performance-stat">
                                <p class="performance-stat-label">Failed</p>
                                <p class="performance-stat-value text-danger">{{ $performance['failed_count'] }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="performance-empty">
                <span class="material-symbols-outlined fs-2 mb-2" style="color: var(--psu-navy);">monitoring</span>
                <h4 class="h5 mb-1" style="color: var(--psu-navy);">No active classes yet</h4>
            </div>
        @endif
    </section>

@endsection
