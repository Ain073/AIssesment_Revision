@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Instructor')
@section('header', 'Dashboard')

@push('styles')
    <x-page-style path="css/pages/instructor/dashboard/index.css" />
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

    <section class="class-performance-section" data-class-performance data-chart-mode="bar">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="brand-text h4 mb-0" style="color: var(--psu-navy);">Active Classes</h2>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-light border rounded-1">{{ $activeClassPerformance->count() }} active</span>
                <div class="chart-mode-switch" role="group" aria-label="Class performance chart type">
                    <button class="chart-mode-button active" data-class-chart-mode="bar" type="button" aria-pressed="true">
                        <span class="material-symbols-outlined fs-6">stacked_bar_chart</span>
                        Bar
                    </button>
                    <button class="chart-mode-button" data-class-chart-mode="pie" type="button" aria-pressed="false">
                        <span class="material-symbols-outlined fs-6">donut_large</span>
                        Pie
                    </button>
                </div>
            </div>
        </div>

        @if ($activeClassPerformance->isNotEmpty())
            <div class="class-performance-grid">
                @foreach ($activeClassPerformance as $performance)
                    @php($class = $performance['class'])
                    <article class="class-performance-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                            <div>
                                <h3 class="h5 fw-bold mb-1" style="color: var(--psu-navy);">{{ $class->class_name }}</h3>
                                <p class="small text-secondary mb-0">{{ $class->subject?->subject_code ?? 'No subject' }}</p>
                            </div>
                            <span class="badge text-bg-light border rounded-1">{{ $class->students_count }} students</span>
                        </div>

                        @if ($performance['has_results'])
                            <div
                                data-chart-view="bar"
                                role="img"
                                aria-label="{{ $performance['passed_percentage'] }} percent passed and {{ $performance['failed_percentage'] }} percent failed"
                            >
                                <div class="performance-track mb-3">
                                    <span class="performance-passed" style="width: {{ $performance['passed_percentage'] }}%;"></span>
                                    <span class="performance-failed" style="width: {{ $performance['failed_percentage'] }}%;"></span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="performance-dot passed"></span>
                                            <span class="small fw-bold text-secondary text-uppercase">Passed</span>
                                        </div>
                                        <div class="performance-value">{{ $performance['passed_percentage'] }}%</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="performance-dot failed"></span>
                                            <span class="small fw-bold text-secondary text-uppercase">Failed</span>
                                        </div>
                                        <div class="performance-value">{{ $performance['failed_percentage'] }}%</div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="performance-pie-layout"
                                data-chart-view="pie"
                                role="img"
                                aria-label="{{ $performance['passed_percentage'] }} percent passed and {{ $performance['failed_percentage'] }} percent failed"
                            >
                                <div class="performance-ring" style="--passed: {{ $performance['passed_percentage'] }};">
                                    <div class="performance-ring-content">
                                        <div class="performance-ring-value">{{ $performance['passed_percentage'] }}%</div>
                                    </div>
                                </div>
                                <div class="performance-breakdown">
                                    <div class="performance-row">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="performance-dot passed"></span>
                                            <span class="small fw-bold text-secondary text-uppercase">Passed</span>
                                        </div>
                                        <div class="performance-value">{{ $performance['passed_percentage'] }}%</div>
                                    </div>
                                    <div class="performance-row">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="performance-dot failed"></span>
                                            <span class="small fw-bold text-secondary text-uppercase">Failed</span>
                                        </div>
                                        <div class="performance-value">{{ $performance['failed_percentage'] }}%</div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="performance-empty">
                                <span>No completed assessment results yet.</span>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            <div class="performance-empty m-0">
                <span>No active classes assigned.</span>
            </div>
        @endif
    </section>

@endsection
