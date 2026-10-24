@extends('layouts.portal')

@section('title', $reportTypeLabel.' Report | AIssessment Department Chair')
@section('header', $reportTypeLabel.' Report')
@section('body_class', 'report-focus-mode')

@php
    $paperOptions = config('reports.paper_sizes');
    $reportColumns = array_replace(config('reports.columns'), config('reports.'.$reportType, []));
    $selectedPaper = array_key_exists(request('paper'), $paperOptions) ? request('paper') : 'long';
    $paper = $paperOptions[$selectedPaper];
@endphp

@push('styles')
    <x-page-style path="css/pages/department-chair/reports/show.css" />
    <style data-page-style>
        :root {
            --report-paper-width: {{ $paper['width'] }};
            --report-paper-height: {{ $paper['height'] }};
            --report-paper-padding: 0.18in;
            --report-preview-ratio: {{ $paper['preview_ratio'] }};
        }

        @media print {
            @page {
                size: landscape;
                margin: {{ $paper['margin'] }};
            }
        }
    </style>
@endpush

@section('content')
    <div class="report-toolbar">
        <div>
            <a class="small fw-semibold text-decoration-none" href="{{ route('department-chair.reports') }}" style="color: var(--psu-navy-2);">
                &larr; Back to Reports
            </a>
            <h1 class="brand-text mt-2 mb-1" style="color: var(--psu-navy);">{{ $reportTypeLabel }} Report</h1>
            <p class="text-secondary mb-0">Finalized report view for department review.</p>
        </div>
        <div class="report-toolbar-actions">
            <span class="badge text-bg-success rounded-1 px-3 py-2">Finalized</span>
            <div class="report-paper-control">
                <label for="paperSize">Paper</label>
                <select class="form-select form-select-sm" id="paperSize">
                    @foreach ($paperOptions as $key => $option)
                        <option value="{{ $key }}" @selected($selectedPaper === $key)>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <span class="report-paper-caption" id="paperSizeCaption">{{ $paper['description'] }}</span>
            </div>
            <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" onclick="window.print()" type="button">
                <span class="material-symbols-outlined fs-5">print</span>
                Export
            </button>
        </div>
    </div>

    <div class="report-sheet-wrap">
        <section class="report-sheet">
            <table class="report-table">
                @include('partials.reports.column-widths')
                <tbody>
                    @include('partials.reports.header-rows', ['courseFieldMode' => 'text'])

                    @include('partials.reports.column-headings')
                    @foreach ($rows as $row)
                        @php
                            $assessment = $row['assessment'];
                            $analytics = $row['analytics'];
                            $rowReport = $row['report'];
                            $reference = strtoupper(($assessment?->reporting_term ?: 'Assessment').' '.($assessment?->title ?? 'Untitled assessment'));
                            $lastColumn = $reportType === \App\Models\Report::TYPE_FORMATIVE
                                ? $rowReport->interventions_done
                                : $rowReport->future_plans_curriculum;
                        @endphp
                        <tr>
                            <td>
                                <div class="report-takers-cell">
                                    {{ $reference }}: {{ $analytics['takers_count'] }}
                                </div>
                            </td>
                            <td class="text-center">{{ $analytics['item_count'] }}</td>
                            <td class="text-center">{{ $analytics['highest_score'] }}</td>
                            <td class="text-center">{{ $analytics['lowest_score'] }}</td>
                            <td>
                                <div class="report-score-stack">
                                    <strong>{{ $analytics['mean_score'] }}</strong>
                                    @if ($reportType === \App\Models\Report::TYPE_FORMATIVE)
                                        <small>{{ $analytics['passing_rate'] }}% passed</small>
                                    @else
                                        <small>{{ $analytics['mean_percentage'] }}%</small>
                                    @endif
                                </div>
                            </td>
                            <td><div class="report-text">{{ $rowReport->concept_most_learned_skills }}</div></td>
                            <td><div class="report-text">{{ $rowReport->concept_least_learned_skills }}</div></td>
                            <td><div class="report-text">{{ $rowReport->issues_concern }}</div></td>
                            <td><div class="report-text">{{ $lastColumn }}</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="report-print-grid-sheet" aria-hidden="true">
                <div class="report-print-grid">
                    <div class="report-print-cell report-print-logo-cell" style="grid-column: 1 / 2; grid-row: 1 / span 2;">
                        <img alt="PSU logo" class="report-logo" src="{{ asset('images/psu-logo-transparent.png') }}">
                    </div>
                    <div class="report-print-cell report-print-title-cell" style="grid-column: 2 / 10; grid-row: 1;">
                        <div class="report-title">STUDENTS PERFORMANCE MONITORING ({{ strtoupper($reportTypeLabel) }} ASSESSMENTS)</div>
                        <div class="report-subtitle">PANGASINAN STATE UNIVERSITY</div>
                    </div>
                    <div class="report-print-cell report-print-period-cell" style="grid-column: 2 / 10; grid-row: 2;">
                        <div class="report-period">{{ $reportMeta['semester'] }} AY {{ $reportMeta['school_year'] }}</div>
                    </div>

                    <div class="report-print-cell report-print-label" style="grid-column: 1 / 2;">Campus</div>
                    <div class="report-print-cell" style="grid-column: 2 / 10;">{{ strtoupper($reportMeta['campus']) }}</div>

                    <div class="report-print-cell report-print-label" style="grid-column: 1 / 2;">College</div>
                    <div class="report-print-cell" style="grid-column: 2 / 6;">{{ strtoupper($reportMeta['college']) }}</div>
                    <div class="report-print-cell report-print-label" style="grid-column: 6 / 7;">Course Code/Title</div>
                    <div class="report-print-cell" style="grid-column: 7 / 10;">{{ $reportMeta['course_code_title'] }}</div>

                    <div class="report-print-cell report-print-label" style="grid-column: 1 / 2;">Department</div>
                    <div class="report-print-cell" style="grid-column: 2 / 6;">{{ strtoupper($reportMeta['department']) }}</div>
                    <div class="report-print-cell report-print-label" style="grid-column: 6 / 7;">No. of Students</div>
                    <div class="report-print-cell" style="grid-column: 7 / 10;">{{ $reportMeta['students_count'] }}</div>

                    <div class="report-print-cell report-print-note-cell" style="grid-column: 1 / 10;">{{ $reportMeta['note'] }}</div>

                    <div class="report-print-cell report-print-column">{{ $reportColumns['takers'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['items'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['high'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['low'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['mean'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['most'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['least'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['issues'] }}</div>
                    <div class="report-print-cell report-print-column">{{ $reportColumns['action'] }}</div>

                    @foreach ($rows as $row)
                        @php
                            $assessment = $row['assessment'];
                            $analytics = $row['analytics'];
                            $rowReport = $row['report'];
                            $reference = strtoupper(($assessment?->reporting_term ?: 'Assessment').' '.($assessment?->title ?? 'Untitled assessment'));
                            $lastColumn = $reportType === \App\Models\Report::TYPE_FORMATIVE
                                ? $rowReport->interventions_done
                                : $rowReport->future_plans_curriculum;
                        @endphp
                        <div class="report-print-data-row">
                            <div class="report-print-cell">
                                <div class="report-print-row-title">{{ $reference }}: {{ $analytics['takers_count'] }}</div>
                            </div>
                            <div class="report-print-cell text-center">{{ $analytics['item_count'] }}</div>
                            <div class="report-print-cell text-center">{{ $analytics['highest_score'] }}</div>
                            <div class="report-print-cell text-center">{{ $analytics['lowest_score'] }}</div>
                            <div class="report-print-cell">
                                <div class="report-score-stack">
                                    <strong>{{ $analytics['mean_score'] }}</strong>
                                    @if ($reportType === \App\Models\Report::TYPE_FORMATIVE)
                                        <small>{{ $analytics['passing_rate'] }}% passed</small>
                                    @else
                                        <small>{{ $analytics['mean_percentage'] }}%</small>
                                    @endif
                                </div>
                            </div>
                            <div class="report-print-cell">
                                <div class="report-print-grid-text">{{ $rowReport->concept_most_learned_skills }}</div>
                            </div>
                            <div class="report-print-cell">
                                <div class="report-print-grid-text">{{ $rowReport->concept_least_learned_skills }}</div>
                            </div>
                            <div class="report-print-cell">
                                <div class="report-print-grid-text">{{ $rowReport->issues_concern }}</div>
                            </div>
                            <div class="report-print-cell">
                                <div class="report-print-grid-text">{{ $lastColumn }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('paperSize')?.addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('paper', this.value);
            window.location.href = url.toString();
        });
    </script>
@endpush
