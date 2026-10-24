@extends('layouts.portal')

@section('title', $pageHeading . ' | AIssessment Student')
@section('header', $pageHeading)

@push('styles')
    <x-page-style path="css/pages/student/placeholder-page.css" />
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
