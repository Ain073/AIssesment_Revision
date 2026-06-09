@extends('layouts.portal')

@section('title', $pageHeading . ' | AIssessment Admin/Dean')
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
            background: linear-gradient(135deg, rgba(0, 17, 58, 0.98), rgba(0, 35, 102, 0.94));
            color: #fff;
        }

        .placeholder-hero p {
            color: rgba(255, 255, 255, 0.82);
            max-width: 64ch;
            margin-bottom: 0;
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
        <p class="small fw-bold text-uppercase mb-2" style="color: rgba(255,255,255,.72); letter-spacing: .04em;">Admin/Dean Module</p>
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
                <span>Sidebar ready</span>
            </div>
            <p class="text-secondary mb-0">Nasa dean portal na ang structure. Susunod na natin ang scoped data at actual backend behavior page by page.</p>
        </article>
    </section>
@endsection
