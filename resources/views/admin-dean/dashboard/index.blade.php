@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Dean')

@section('topbar-leading')
    @include('partials.dashboard-view-selector')
@endsection

@push('styles')
    <style>
        .hero-card {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(120deg, rgba(4, 25, 132, 0.98) 0%, rgba(8, 48, 210, 0.96) 58%, rgba(142, 137, 113, 0.96) 125%),
                linear-gradient(180deg, #03104f 0%, #0927d8 62%, #8f8a73 100%);
            color: #fff;
            border: 1px solid rgba(255, 218, 39, 0.34);
            border-radius: 0.5rem;
            box-shadow: 0 18px 34px rgba(0, 26, 112, 0.18);
            padding: 2rem 2.25rem;
        }

        .hero-card::after {
            position: absolute;
            inset: 0 0 0 auto;
            width: 38%;
            content: '';
            background: linear-gradient(135deg, rgba(255, 245, 191, 0.18), rgba(255, 255, 255, 0.06));
            transform: skewX(-12deg) translateX(22%);
        }

        .hero-card > * {
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: clamp(2.4rem, 3vw, 3.25rem);
            line-height: 1;
            margin-bottom: 0;
        }

        .hero-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, 260px);
            gap: 1.5rem;
            align-items: center;
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

        .hero-copy {
            color: rgba(255, 255, 255, 0.82);
            max-width: 60ch;
            margin-bottom: 0;
        }

        .hero-eyebrow {
            color: rgba(255, 245, 191, 0.92);
            letter-spacing: 0.04em;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.85rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }

        .stat-card {
            padding: 1rem;
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

        @media (max-width: 991.98px) {
            .hero-layout {
                grid-template-columns: 1fr;
            }

            .stat-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.65rem;
            }

            .stat-card {
                padding: 0.75rem;
            }

            .stat-label {
                font-size: 0.64rem;
                line-height: 1.15;
                margin-bottom: 0.35rem;
            }

            .stat-value {
                font-size: 1.3rem;
            }
        }
    </style>
@endpush

@section('content')
    <section class="hero-card">
        <div class="hero-layout">
            <div>
                <p class="small fw-bold text-uppercase mb-2 hero-eyebrow">Welcome back</p>
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

@endsection
