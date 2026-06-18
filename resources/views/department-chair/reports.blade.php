@extends('layouts.portal')

@section('title', 'Reports | AIssessment Department Chair')
@section('header', 'Reports')

@push('styles')
    <style>
        .reports-hero,
        .reports-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
        }

        .reports-hero {
            padding: 1.75rem;
            background:
                linear-gradient(118deg, rgba(5, 33, 171, 0.98) 0%, rgba(13, 49, 221, 0.96) 58%, rgba(226, 196, 48, 0.9) 150%),
                radial-gradient(circle at 100% 0%, rgba(255, 226, 76, 0.44) 0%, rgba(255, 226, 76, 0) 34%),
                linear-gradient(180deg, #021063 0%, #0828c9 58%, #d4b736 100%);
            color: #fff;
            border-color: rgba(255, 218, 39, 0.28);
            box-shadow: 0 16px 34px rgba(9, 39, 216, 0.14);
        }

        .reports-hero p {
            color: rgba(255, 255, 255, 0.82);
            max-width: 64ch;
            margin-bottom: 0;
        }

        .reports-hero .hero-eyebrow {
            color: rgba(255, 245, 191, 0.92);
            letter-spacing: 0.04em;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .reports-card {
            padding: 1.5rem;
        }

        @media (max-width: 991.98px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="reports-hero">
        <p class="small fw-bold text-uppercase mb-2 hero-eyebrow">Department Chair Module</p>
        <h2 class="brand-text h1 mb-3">Reports</h2>
        <p>Finalized instructor reports submitted for department monitoring will be organized here.</p>
    </section>

    <section class="reports-grid">
        <article class="reports-card">
            <h3 class="brand-text h4 mb-2" style="color: var(--psu-navy);">Formative Reports</h3>
            <p class="text-secondary mb-0">Review finalized formative reports once report generation is connected.</p>
        </article>
        <article class="reports-card">
            <h3 class="brand-text h4 mb-2" style="color: var(--psu-navy);">Summative Reports</h3>
            <p class="text-secondary mb-0">Review finalized summative reports once assessment submissions are available.</p>
        </article>
        <article class="reports-card">
            <h3 class="brand-text h4 mb-2" style="color: var(--psu-navy);">Monitoring</h3>
            <p class="text-secondary mb-0">Track report outputs across instructors under the chair scope.</p>
        </article>
    </section>
@endsection
