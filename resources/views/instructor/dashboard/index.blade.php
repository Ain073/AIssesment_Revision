@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Instructor')
@section('header', 'Instructor Dashboard')

@push('styles')
    <style>
        .hero-card {
            background:
                linear-gradient(118deg, rgba(5, 33, 171, 0.98) 0%, rgba(13, 49, 221, 0.96) 58%, rgba(226, 196, 48, 0.9) 150%),
                radial-gradient(circle at 100% 0%, rgba(255, 226, 76, 0.44) 0%, rgba(255, 226, 76, 0) 34%),
                linear-gradient(180deg, #021063 0%, #0828c9 58%, #d4b736 100%);
            color: #fff;
            border: 1px solid rgba(255, 218, 39, 0.28);
            border-radius: 0.5rem;
            box-shadow: 0 16px 34px rgba(9, 39, 216, 0.14);
            padding: 1.75rem;
        }

        .hero-title {
            font-size: clamp(2.3rem, 3vw, 3.2rem);
            line-height: 1;
            margin-bottom: 0.75rem;
        }

        .hero-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 420px);
            gap: 1.5rem;
            align-items: center;
        }

        .hero-eyebrow {
            color: rgba(255, 245, 191, 0.92);
            letter-spacing: 0.04em;
        }

        .hero-actions {
            display: grid;
            gap: 0.75rem;
        }

        .hero-action-link {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.8rem 0.95rem;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 0.45rem;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            text-decoration: none;
        }

        .hero-action-link:hover,
        .hero-action-link:focus {
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
        }

        .hero-action-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 40px;
            border-radius: 0.35rem;
            background: rgba(255, 245, 191, 0.2);
            color: var(--psu-gold);
        }

        .stat-card,
        .class-performance-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 10px 24px rgba(0, 26, 112, 0.06);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            padding: 1.25rem;
        }

        .stat-label {
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 0.65rem;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--psu-navy);
        }

        .flash-alert {
            border-radius: 0.5rem;
        }

        .class-performance-section {
            margin-top: 1.5rem;
        }

        .class-performance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .class-performance-card {
            min-height: 210px;
            padding: 1.25rem;
        }

        .chart-mode-switch {
            display: flex;
            align-items: center;
            padding: 3px;
            border: 1px solid #c6d2f5;
            border-radius: 0.4rem;
            background: #fff;
        }

        .chart-mode-button {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 34px;
            padding: 0.35rem 0.65rem;
            border: 0;
            border-radius: 0.25rem;
            background: transparent;
            color: var(--psu-muted);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .chart-mode-button.active {
            background: var(--psu-navy-2);
            color: #fff;
        }

        [data-class-performance][data-chart-mode="bar"] [data-chart-view="pie"],
        [data-class-performance][data-chart-mode="pie"] [data-chart-view="bar"] {
            display: none;
        }

        .performance-track {
            display: flex;
            height: 14px;
            overflow: hidden;
            background: #e5e7eb;
            border-radius: 3px;
        }

        .performance-passed {
            background: #198754;
        }

        .performance-failed {
            background: #dc3545;
        }

        .performance-pie-layout {
            display: grid;
            grid-template-columns: 112px minmax(0, 1fr);
            align-items: center;
            gap: 1.25rem;
        }

        .performance-ring {
            position: relative;
            display: grid;
            place-items: center;
            width: 112px;
            aspect-ratio: 1;
            border-radius: 50%;
            background: conic-gradient(
                #198754 0 calc(var(--passed) * 1%),
                #dc3545 calc(var(--passed) * 1%) 100%
            );
        }

        .performance-ring::after {
            position: absolute;
            inset: 12px;
            content: '';
            background: #fff;
            border-radius: 50%;
            box-shadow: inset 0 0 0 1px #edf0f7;
        }

        .performance-ring-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .performance-ring-value {
            color: var(--psu-navy);
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1;
        }

        .performance-breakdown {
            display: grid;
            gap: 0.7rem;
        }

        .performance-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 0.55rem;
            border-bottom: 1px solid #edf0f7;
        }

        .performance-value {
            color: var(--psu-navy);
            font-size: 1.2rem;
            font-weight: 800;
        }

        .performance-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex: 0 0 10px;
        }

        .performance-dot.passed {
            background: #198754;
        }

        .performance-dot.failed {
            background: #dc3545;
        }

        .performance-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 84px;
            border: 1px dashed #b9c5e7;
            border-radius: 0.4rem;
            background: #f8faff;
            color: var(--psu-muted);
            padding: 1rem;
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .hero-layout {
                grid-template-columns: 1fr;
            }

            .stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .class-performance-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .stat-grid {
                grid-template-columns: 1fr;
            }

            .performance-pie-layout {
                grid-template-columns: 1fr;
                justify-items: center;
            }

            .performance-breakdown {
                width: 100%;
            }
        }
    </style>
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
                            <span class="small text-white-50">{{ $action['description'] }}</span>
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
                        <p class="small text-secondary mb-0">{{ $stat['caption'] }}</p>
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
