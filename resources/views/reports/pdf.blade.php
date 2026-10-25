@php
    $reportColumns = array_replace(
        config('reports.columns'),
        config('reports.'.$reportType, []),
        $rows->first()['report']?->column_headers ?? [],
    );
    $columnWidths = [
        'takers' => 12, 'items' => 6, 'high' => 6, 'low' => 6, 'mean' => 9,
        'most' => 18, 'least' => 18, 'issues' => 13, 'action' => 12,
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTypeLabel }} Report</title>
    <style>
        @page { margin: 0.35in 0.25in; }
        body { margin: 0; color: #111827; font: 7pt Arial, Helvetica, sans-serif; }
        .document-control { height: 0.4in; padding-right: 0.1in; color: #6b7280; font-size: 7pt; font-style: italic; line-height: 1.05; text-align: right; }
        .document-control div { min-height: 0.12in; }
        .report-table, .signatures { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 0.6pt solid #111827; padding: 3pt; vertical-align: top; overflow-wrap: break-word; }
        .report-table th { font-weight: bold; text-align: center; text-transform: uppercase; }
        .report-table thead { display: table-header-group; }
        .report-table tr { page-break-inside: avoid; }
        .report-logo-cell { text-align: center; vertical-align: middle !important; }
        .report-logo { width: 0.62in; height: 0.62in; object-fit: contain; }
        .report-heading-row th { height: 36pt; padding-top: 2pt; padding-bottom: 2pt; vertical-align: middle; }
        .report-period-row th { height: 20pt; padding-top: 1pt; padding-bottom: 1pt; }
        .report-title { font-size: 10pt; }
        .report-subtitle { font-size: 6pt; }
        .report-period { font-size: 8pt; text-align: center; vertical-align: middle !important; }
        .report-note { font-size: 5.5pt; font-style: italic; text-align: center; }
        .report-matrix-head th { font-size: 7pt; }
        .report-takers-cell, .report-score-stack { text-align: center; }
        .report-score-stack strong, .report-score-stack small { display: block; }
        .report-text { white-space: pre-line; }
        .center { text-align: center; }
        .signatures { margin-top: 1pt; border: 0.6pt solid #111827; border-top: 5pt solid #777; page-break-inside: avoid; }
        .signatures td { width: 50%; padding: 12pt 18pt 7pt; vertical-align: top; font-size: 9pt; }
        .signature-name { margin-top: 20pt; border-bottom: 0.6pt solid #111827; padding-bottom: 3pt; text-align: center; }
        .signature-role { padding-top: 3pt; text-align: center; }
    </style>
</head>
<body>
    <div class="document-control">
        <div>{{ $reportMeta['document_code'] }}</div>
        <div>{{ $reportMeta['document_revision'] }}</div>
        <div>{{ $reportMeta['document_date'] }}</div>
    </div>

    <table class="report-table">
        <colgroup>
            @foreach ($columnWidths as $width)
                <col width="{{ $width }}%">
            @endforeach
        </colgroup>
        <tbody>
            @include('partials.reports.header-rows', ['courseFieldMode' => 'text'])
        </tbody>
    </table>

    <table class="report-table">
        <colgroup>
            @foreach ($columnWidths as $width)
                <col width="{{ $width }}%">
            @endforeach
        </colgroup>
        <thead>
            <tr class="report-matrix-head">
                @foreach ($reportColumns as $key => $label)
                    <th width="{{ $columnWidths[$key] }}%">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php
                    $assessment = $row['assessment'];
                    $analytics = $row['analytics'];
                    $report = $row['report'];
                    $reference = strtoupper(($assessment?->reporting_term ?: 'Assessment').' '.($assessment?->title ?? 'Untitled assessment'));
                    $lastColumn = $reportType === \App\Models\Report::TYPE_FORMATIVE
                        ? $report->interventions_done
                        : $report->future_plans_curriculum;
                @endphp
                <tr>
                    <td class="center">{{ $reference }}: {{ $analytics['takers_count'] }}</td>
                    <td class="center">{{ $analytics['item_count'] }}</td>
                    <td class="center">{{ $analytics['highest_score'] }}</td>
                    <td class="center">{{ $analytics['lowest_score'] }}</td>
                    <td class="report-score-stack">
                        <strong>{{ $analytics['mean_score'] }}</strong>
                        <small>{{ $reportType === \App\Models\Report::TYPE_FORMATIVE ? $analytics['passing_rate'].'% passed' : $analytics['mean_percentage'].'%' }}</small>
                    </td>
                    <td class="report-text">{{ $report->concept_most_learned_skills }}</td>
                    <td class="report-text">{{ $report->concept_least_learned_skills }}</td>
                    <td class="report-text">{{ $report->issues_concern }}</td>
                    <td class="report-text">{{ $lastColumn }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                Prepared by:
                <div class="signature-name">{{ $reportMeta['prepared_by_name'] }}</div>
                <div class="signature-role">Faculty</div>
            </td>
            <td>
                Reviewed by:
                <div class="signature-name">{{ $reportMeta['reviewed_by_name'] }}</div>
                <div class="signature-role">Department Chairperson</div>
            </td>
        </tr>
    </table>
</body>
</html>
