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

        .hero-copy {
            color: rgba(255, 255, 255, 0.82);
            max-width: 60ch;
            margin-bottom: 0;
        }

        .hero-eyebrow {
            color: rgba(255, 245, 191, 0.92);
            letter-spacing: 0.04em;
        }

        .stat-card,
        .info-card,
        .quick-action-card,
        .note-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card,
        .info-card,
        .note-card {
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

        .section-title {
            color: var(--psu-navy);
            margin-bottom: 1rem;
        }

        .eyebrow {
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .action-grid,
        .note-list {
            display: grid;
            gap: 1rem;
        }

        .quick-action-card {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            text-decoration: none;
            color: inherit;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            padding: 1.5rem;
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(0, 17, 58, 0.08);
            color: inherit;
        }

        .flash-alert {
            border-radius: 0.5rem;
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
    @if (session('status'))
        <div class="alert alert-success flash-alert">{{ session('status') }}</div>
    @endif

    <section class="hero-card">
        <p class="small fw-bold text-uppercase mb-2 hero-eyebrow">Welcome Back</p>
        <h2 class="brand-text hero-title">{{ $user->displayName() }}</h2>   
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
            <p class="eyebrow">Quick Access</p>
            <h3 class="brand-text h4 section-title">Student Features</h3>

            <div class="action-grid">
                @foreach ($quickActions as $action)
                    <a class="quick-action-card" href="{{ $action['href'] }}">
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
            <p class="eyebrow">Manuscript Scope</p>
            <h3 class="brand-text h4 section-title">What Students Can Do</h3>

            <div class="note-list">
                @foreach ($studentNotes as $note)
                    <article class="note-card">
                        <p class="fw-bold mb-1" style="color: var(--psu-navy);">{{ $note['title'] }}</p>
                        <p class="text-secondary mb-0">{{ $note['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </article>
    </section>
@endsection
