@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Department Chair')
@section('header', 'Department Chair Dashboard')

@push('styles')
    <style>
        .hero-card {
            background: linear-gradient(135deg, rgba(0, 17, 58, 0.98), rgba(0, 35, 102, 0.94));
            color: #fff;
            border: 1px solid rgba(0, 17, 58, 0.12);
            border-radius: 0.5rem;
            padding: 1.75rem;
        }

        .hero-copy {
            color: rgba(255, 255, 255, 0.82);
            max-width: 60ch;
            margin-bottom: 0;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card,
        .info-card,
        .action-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
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

        .workspace-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-card {
            padding: 1.5rem;
        }

        .eyebrow {
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .section-title {
            color: var(--psu-navy);
            margin-bottom: 1rem;
        }

        .action-grid,
        .focus-list {
            display: grid;
            gap: 1rem;
        }

        .action-card {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.25rem;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            color: inherit;
            box-shadow: 0 14px 28px rgba(0, 17, 58, 0.08);
        }

        .focus-item {
            padding: 1rem 0 0;
            border-top: 1px solid #e4e8f0;
        }

        .focus-item:first-child {
            padding-top: 0;
            border-top: 0;
        }

        @media (max-width: 991.98px) {
            .stat-grid,
            .workspace-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="hero-card">
        <p class="small fw-bold text-uppercase mb-2" style="color: rgba(255,255,255,.72); letter-spacing: .04em;">Department-Level View</p>
        <h2 class="brand-text h1 mb-3">{{ $user->displayName() }}</h2>
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

    <section class="workspace-grid">
        <article class="info-card">
            <p class="eyebrow">Next Step</p>
            <h3 class="brand-text h4 section-title">Quick Actions</h3>
            <div class="action-grid">
                @foreach ($quickActions as $action)
                    <a class="action-card" href="{{ $action['href'] }}">
                        <span class="icon-tile"><span class="material-symbols-outlined">{{ $action['icon'] }}</span></span>
                        <div>
                            <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $action['label'] }}</p>
                            <p class="text-secondary mb-0">{{ $action['description'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </article>

        <article class="info-card">
            <p class="eyebrow">Current Focus</p>
            <h3 class="brand-text h4 section-title">What This Role Handles</h3>
            <div class="focus-list">
                @foreach ($focusItems as $item)
                    <div class="focus-item">
                        <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $item['title'] }}</p>
                        <p class="text-secondary mb-0">{{ $item['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection
