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
        @page { margin: 0.75in 0.25in 0.35in; }
        body { margin: 0; color: #111827; font: 7pt Arial, Helvetica, sans-serif; }
        .document-control { position: fixed; top: -0.4in; right: 0.1in; height: 0.4in; color: #6b7280; font-size: 7pt; font-style: italic; line-height: 1.05; text-align: right; }
        .document-control div { min-height: 0.12in; }
        .report-table, .signatures { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 0.6pt solid #111827; padding: 3pt; vertical-align: top; overflow-wrap: break-word; }
        .report-table th { font-weight: bold; text-align: center; text-transform: uppercase; }
        .report-table tr { page-break-inside: avoid; }
        .report-row-continues td { border-bottom: 0; padding-bottom: 0; }
        .report-row-continuation td { border-top: 0; padding-top: 0; }
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

    <table class="report-table report-matrix">
        <colgroup>
            @foreach ($columnWidths as $width)
                <col width="{{ $width }}%">
            @endforeach
        </colgroup>
        <tbody>
            <tr class="report-matrix-head">
                @foreach ($reportColumns as $key => $label)
                    <th width="{{ $columnWidths[$key] }}%">{{ $label }}</th>
                @endforeach
            </tr>
            @foreach ($pdfRows as $part)
                @php
                    $row = $part['row'];
                    $assessment = $row['assessment'];
                    $analytics = $row['analytics'];
                    $reference = strtoupper(($assessment?->reporting_term ?: 'Assessment').' '.($assessment?->title ?? 'Untitled assessment'));
                @endphp
                <tr @class(['report-row-continues' => ! $part['last'], 'report-row-continuation' => ! $part['first']])>
                    <td class="center">@if ($part['first']){{ $reference }}: {{ $analytics['takers_count'] }}@endif</td>
                    <td class="center">{{ $part['first'] ? $analytics['item_count'] : '' }}</td>
                    <td class="center">{{ $part['first'] ? $analytics['highest_score'] : '' }}</td>
                    <td class="center">{{ $part['first'] ? $analytics['lowest_score'] : '' }}</td>
                    <td class="report-score-stack">
                        @if ($part['first'])
                            <strong>{{ $analytics['mean_score'] }}</strong>
                            <small>{{ $reportType === \App\Models\Report::TYPE_FORMATIVE ? $analytics['passing_rate'].'% passed' : $analytics['mean_percentage'].'%' }}</small>
                        @endif
                    </td>
                    <td class="report-text">{{ $part['most'] }}</td>
                    <td class="report-text">{{ $part['least'] }}</td>
                    <td class="report-text">{{ $part['issues'] }}</td>
                    <td class="report-text">{{ $part['action'] }}</td>
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
