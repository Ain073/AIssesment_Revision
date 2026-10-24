@php
    $pageConfig = [
        'paperOptions' => collect($paperOptions)->map(fn ($option) => $option['description']),
        'reportType' => $reportType,
    ];
@endphp

<script id="reportSheetConfig" type="application/json">@json($pageConfig)</script>
<script src="{{ asset('js/report-sheet.js') }}?v={{ filemtime(public_path('js/report-sheet.js')) }}"></script>
