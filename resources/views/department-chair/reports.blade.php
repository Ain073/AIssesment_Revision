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
            background: linear-gradient(135deg, rgba(0, 17, 58, 0.98), rgba(0, 35, 102, 0.94));
            color: #fff;
        }

        .reports-hero p {
            color: rgba(255, 255, 255, 0.82);
            max-width: 64ch;
            margin-bottom: 0;
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
        <p class="small fw-bold text-uppercase mb-2" style="color: rgba(255,255,255,.72); letter-spacing: .04em;">Department Chair Module</p>
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
