@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Student')
@section('header', 'Student Dashboard')

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
            grid-template-columns: minmax(0, 1fr) minmax(220px, 260px);
            gap: 1.5rem;
            align-items: center;
        }

        .hero-eyebrow {
            color: rgba(255, 245, 191, 0.92);
            letter-spacing: 0.04em;
        }

        .stat-card,
        .performance-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
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

        .section-title {
            color: var(--psu-navy);
            margin-bottom: 1rem;
        }

        .performance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .performance-card {
            padding: 1.25rem;
        }

        .performance-heading {
            color: var(--psu-navy);
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .performance-percent {
            font-size: 1.8rem;
            font-weight: 900;
            line-height: 1;
        }

        .performance-track {
            height: 0.65rem;
            overflow: hidden;
            background: #e5e7eb;
            border-radius: 999px;
        }

        .performance-fill {
            height: 100%;
            border-radius: inherit;
        }

        .performance-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .performance-stat {
            padding-top: 0.75rem;
            border-top: 1px solid #edf0f7;
        }

        .performance-stat-label {
            color: var(--psu-muted);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
        }

        .performance-stat-value {
            color: var(--psu-navy);
            font-weight: 900;
            margin-bottom: 0;
        }

        .performance-empty {
            border: 1px dashed #b9c5e7;
            border-radius: 0.5rem;
            background: #fbfcff;
            padding: 1.5rem;
            text-align: center;
        }

        .flash-alert {
            border-radius: 0.5rem;
        }

        @media (max-width: 991.98px) {
            .hero-layout {
                grid-template-columns: 1fr;
            }

            .stat-grid,
            .performance-grid {
                grid-template-columns: 1fr;
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
                        $barColor = ! $hasScore
                            ? '#94a3b8'
                            : ($percentage >= 75 ? '#198754' : '#dc3545');
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
