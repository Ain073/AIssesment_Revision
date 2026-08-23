@extends('layouts.portal')

@section('title', $reportTypeLabel.' Report | AIssessment Department Chair')
@section('header', $reportTypeLabel.' Report')
@section('body_class', 'report-focus-mode')

@php
    $paperOptions = [
        'a4' => [
            'label' => 'A4',
            'description' => '11.69 x 8.27 in',
            'width' => '11.69in',
            'height' => '8.27in',
            'margin' => '0.18in',
            'preview_ratio' => '0.9',
        ],
        'short' => [
            'label' => 'Short / Letter',
            'description' => '11 x 8.5 in',
            'width' => '11in',
            'height' => '8.5in',
            'margin' => '0.18in',
            'preview_ratio' => '0.846',
        ],
        'long' => [
            'label' => 'Long / Legal',
            'description' => '13 x 8.5 in',
            'width' => '13in',
            'height' => '8.5in',
            'margin' => '0.2in',
            'preview_ratio' => '1',
        ],
    ];
    $selectedPaper = array_key_exists(request('paper'), $paperOptions) ? request('paper') : 'long';
    $paper = $paperOptions[$selectedPaper];
@endphp

@push('styles')
    <style>
        :root {
            --report-paper-width: {{ $paper['width'] }};
            --report-paper-height: {{ $paper['height'] }};
            --report-paper-padding: 0.18in;
            --report-preview-ratio: {{ $paper['preview_ratio'] }};
        }

        body.report-focus-mode {
            overflow-x: auto;
        }

        body.report-focus-mode .sidebar,
        body.report-focus-mode .topbar {
            display: none !important;
        }

        body.report-focus-mode .main-content {
            margin-left: 0;
            padding-top: 0;
            min-height: 100vh;
        }

        body.report-focus-mode .page-container {
            max-width: none;
            width: 100%;
            padding: 0.5rem;
        }

        .report-toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .report-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .report-paper-control {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            min-height: 40px;
            padding: 0.35rem 0.6rem;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            background: #fff;
        }

        .report-paper-control label {
            font-size: 0.74rem;
            font-weight: 800;
            color: #4b5563;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-paper-control select {
            width: auto;
            min-width: 140px;
            border: 0;
            padding: 0;
            color: var(--psu-navy);
            font-weight: 700;
            background-color: transparent;
            box-shadow: none;
        }

        .report-paper-caption {
            min-width: 86px;
            font-size: 0.72rem;
            color: #6b7280;
            white-space: nowrap;
        }

        .report-sheet-wrap {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 18px 36px rgba(0, 26, 112, 0.08);
            overflow-x: auto;
            padding: 0.6rem;
        }

        .report-sheet {
            width: clamp(
                var(--report-paper-width),
                calc((100vw - 2.2rem) * var(--report-preview-ratio)),
                calc(100vw - 2.2rem)
            );
            min-width: var(--report-paper-width);
            min-height: var(--report-paper-height);
            margin: 0 auto;
            padding: var(--report-paper-padding);
            color: #1f2937;
            background: #fff;
            border: 2px solid #111827;
            box-sizing: border-box;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-bottom: 2px solid #111827;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #111827;
            padding: 0.28rem 0.36rem;
            vertical-align: top;
            font-size: 0.7rem;
            line-height: 1.28;
            overflow-wrap: anywhere;
        }

        .report-table tr:first-child th,
        .report-table tr:first-child td,
        .report-table tr:nth-child(2) th,
        .report-table tr:nth-child(2) td,
        .report-table tr:nth-child(5) th,
        .report-table tr:nth-child(5) td {
            border-bottom-width: 2px;
        }

        .report-table tr:first-child th,
        .report-table tr:first-child td {
            border-top-width: 2px;
        }

        .report-table th {
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
        }

        .report-table tr:nth-child(-n + 5) th {
            padding-left: 0.45rem;
            padding-right: 0.45rem;
            white-space: nowrap;
        }

        .report-logo-cell {
            text-align: center;
            vertical-align: middle !important;
        }

        .report-logo {
            width: 62px;
            height: 62px;
            object-fit: contain;
        }

        .report-title {
            font-size: 1.18rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .report-subtitle {
            font-size: 0.66rem;
            font-weight: 500;
        }

        .report-period {
            font-size: 0.8rem !important;
            font-weight: 800;
        }

        .report-note {
            font-style: italic;
            text-align: center;
            border-top: 2px solid #111827 !important;
            border-bottom: 3px double #111827 !important;
        }

        .report-matrix-head th {
            border-top: 0;
            border-bottom: 2px solid #111827;
        }

        .report-table tr:last-child td {
            border-bottom-width: 2px;
        }

        .report-col-takers { width: 12%; }
        .report-col-items { width: 6%; }
        .report-col-high { width: 6%; }
        .report-col-low { width: 6%; }
        .report-col-mean { width: 9%; }
        .report-col-most { width: 18%; }
        .report-col-least { width: 18%; }
        .report-col-issues { width: 13%; }
        .report-col-action { width: 12%; }

        .report-takers-cell {
            min-height: 135px;
            display: grid;
            place-items: center;
            text-align: center;
            color: #4b5563;
            text-transform: uppercase;
        }

        .report-score-stack {
            display: grid;
            gap: 0.12rem;
            text-align: center;
        }

        .report-score-stack small {
            color: #4b5563;
            font-size: 0.64rem;
        }

        .report-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        @media print {
            @page {
                size: {{ $paper['width'] }} {{ $paper['height'] }};
                margin: {{ $paper['margin'] }};
            }

            body {
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body::before,
            body::after {
                content: "";
                position: fixed;
                left: 0;
                right: 0;
                z-index: 9999;
                pointer-events: none;
            }

            body::before {
                top: 0;
                border-top: 2px solid #111827;
            }

            body::after {
                bottom: 0;
                border-bottom: 2px solid #111827;
            }

            .sidebar,
            .topbar,
            .report-toolbar {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }

            .page-container {
                max-width: none !important;
                padding: 0 !important;
            }

            .report-sheet-wrap {
                border: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
                overflow: visible !important;
            }

            .report-sheet {
                border: 0;
                width: 100%;
                min-width: 100%;
                height: auto !important;
                min-height: 0 !important;
                padding: 0;
            }

            .report-table {
                border-collapse: separate !important;
                border-spacing: 0 !important;
                -webkit-box-decoration-break: clone;
                box-decoration-break: clone;
            }

            .report-table th,
            .report-table td {
                border: 0 !important;
                border-right: 1px solid #111827 !important;
                border-bottom: 1px solid #111827 !important;
                -webkit-box-decoration-break: clone;
                box-decoration-break: clone;
            }

            .report-table tr > :first-child {
                border-left: 1px solid #111827 !important;
            }

            .report-table tr:first-child > * {
                border-top: 2px solid #111827 !important;
                border-bottom-width: 2px !important;
            }

            .report-table tr:nth-child(2) > *,
            .report-table tr:nth-child(5) > *,
            .report-matrix-head > * {
                border-bottom-width: 2px !important;
            }

            .report-note {
                border-top-width: 2px !important;
                border-bottom: 3px double #111827 !important;
            }

            .report-table tr:last-child > * {
                border-bottom-width: 2px !important;
            }

            .report-table {
                break-inside: auto;
                page-break-inside: auto;
            }

            .report-table tr:nth-child(n + 8) {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .report-table td,
            .report-table th {
                break-inside: auto;
                page-break-inside: auto;
            }

            .report-takers-cell {
                min-height: 0.85in;
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
                <colgroup>
                    <col class="report-col-takers">
                    <col class="report-col-items">
                    <col class="report-col-high">
                    <col class="report-col-low">
                    <col class="report-col-mean">
                    <col class="report-col-most">
                    <col class="report-col-least">
                    <col class="report-col-issues">
                    <col class="report-col-action">
                </colgroup>
                <tbody>
                    <tr>
                        <td class="report-logo-cell" rowspan="2">
                            <img alt="PSU logo" class="report-logo" src="{{ asset('images/psu-logo-transparent.png') }}">
                        </td>
                        <th colspan="8">
                            <div class="report-title">STUDENTS PERFORMANCE MONITORING ({{ strtoupper($reportTypeLabel) }} ASSESSMENTS)</div>
                            <div class="report-subtitle">PANGASINAN STATE UNIVERSITY</div>
                        </th>
                    </tr>
                    <tr>
                        <th class="report-period" colspan="8">{{ $reportMeta['semester'] }} AY {{ $reportMeta['school_year'] }}</th>
                    </tr>
                    <tr>
                        <th>Campus</th>
                        <td colspan="8">{{ strtoupper($reportMeta['campus']) }}</td>
                    </tr>
                    <tr>
                        <th>College</th>
                        <td colspan="4">{{ strtoupper($reportMeta['college']) }}</td>
                        <th colspan="2">Course Code/Title</th>
                        <td colspan="2">{{ $reportMeta['course_code_title'] }}</td>
                    </tr>
                    <tr>
                        <th>Department</th>
                        <td colspan="4">{{ strtoupper($reportMeta['department']) }}</td>
                        <th colspan="2">No. of Students</th>
                        <td colspan="2">{{ $reportMeta['students_count'] }}</td>
                    </tr>
                    <tr>
                        <td class="report-note" colspan="9">{{ $reportMeta['note'] }}</td>
                    </tr>
                    <tr class="report-matrix-head">
                        <th>Total Number of Students Who Took the Assessment</th>
                        <th>Number of Items</th>
                        <th>Highest Score</th>
                        <th>Lowest Score</th>
                        <th>{{ $reportType === \App\Models\Report::TYPE_FORMATIVE ? 'Mean Score and Percentage of Students Who Passed' : 'Mean Score' }}</th>
                        <th>Concepts / Skills Most Learned</th>
                        <th>Concepts / Skills Least Learned</th>
                        <th>Issues / Concerns Encountered</th>
                        <th>{{ $reportType === \App\Models\Report::TYPE_FORMATIVE ? 'Interventions Done' : 'Future Plans to Improve the Curriculum' }}</th>
                    </tr>
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
