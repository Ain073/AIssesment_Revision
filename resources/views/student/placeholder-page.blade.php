@extends('layouts.portal')

@section('title', $pageHeading . ' | AIssessment Student')
@section('header', $pageHeading)

@push('styles')
    <style>
        .placeholder-hero,
        .placeholder-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }

        .placeholder-hero {
            padding: 1.75rem;
            background:
                linear-gradient(118deg, rgba(5, 33, 171, 0.98) 0%, rgba(13, 49, 221, 0.96) 58%, rgba(226, 196, 48, 0.9) 150%),
                radial-gradient(circle at 100% 0%, rgba(255, 226, 76, 0.44) 0%, rgba(255, 226, 76, 0) 34%),
                linear-gradient(180deg, #021063 0%, #0828c9 58%, #d4b736 100%);
            color: #fff;
            border-color: rgba(255, 218, 39, 0.28);
            box-shadow: 0 16px 34px rgba(9, 39, 216, 0.14);
        }

        .placeholder-hero p {
            color: rgba(255, 255, 255, 0.82);
            max-width: 64ch;
            margin-bottom: 0;
        }

        .placeholder-hero .hero-eyebrow {
            color: rgba(255, 245, 191, 0.92);
            letter-spacing: 0.04em;
        }

        .placeholder-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .placeholder-card {
            padding: 1.5rem;
        }

        .placeholder-list {
            display: grid;
            gap: 0.9rem;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .placeholder-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
        }

        .placeholder-list .material-symbols-outlined {
            color: #198754;
            font-size: 1.2rem;
            margin-top: 0.05rem;
        }

        .placeholder-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            padding: 0.55rem 0.85rem;
            background: #eff4ff;
            color: var(--psu-navy);
            font-weight: 700;
        }

        @media (max-width: 991.98px) {
            .placeholder-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="placeholder-hero">
        <p class="small fw-bold text-uppercase mb-2 hero-eyebrow">Student Module</p>
        <h2 class="brand-text h1 mb-3">{{ $pageHeading }}</h2>
        <p>{{ $pageDescription }}</p>
    </section>

    <section class="placeholder-grid">
        <article class="placeholder-card">
            <h3 class="brand-text h4 mb-3" style="color: var(--psu-navy);">Planned In This Page</h3>
            <ul class="placeholder-list">
                @foreach ($pageChecklist as $item)
                    <li>
                        <span class="material-symbols-outlined">check_circle</span>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </article>

        <article class="placeholder-card">
            <h3 class="brand-text h4 mb-3" style="color: var(--psu-navy);">Current Status</h3>
            <div class="placeholder-badge mb-3">
                <span class="material-symbols-outlined">construction</span>
                <span>Page ready</span>
            </div>
            <p class="text-secondary mb-0">Nakaayos na ang student portal structure. Susunod na natin isa-isahin ang actual class membership, assessment-taking flow, at released results.</p>
        </article>
    </section>
@endsection
