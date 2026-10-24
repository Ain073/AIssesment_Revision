<colgroup>
    @foreach (array_keys($reportColumns) as $column)
        <col class="report-col-{{ $column }}">
    @endforeach
</colgroup>
