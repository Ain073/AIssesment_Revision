@extends('layouts.portal')

@section('title', 'Dashboard | AIssessment Admin/Dean')
@section('header', 'Admin/Dean Dashboard')

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
            grid-template-columns: repeat(4, minmax(0, 1fr));
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
            grid-template-columns: 1fr;
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

        .action-grid {
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

        @media (max-width: 991.98px) {
            .stat-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="hero-card">
        <p class="small fw-bold text-uppercase mb-2 hero-eyebrow">College-Level View</p>
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

    </section>
@endsection
