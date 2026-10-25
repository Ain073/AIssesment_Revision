<tr class="report-matrix-head">
    @foreach ($reportColumns as $key => $label)
        <th @if ($printMirror ?? false) class="report-print-mirror" data-print-field-name="column_headers[{{ $key }}]" @endif>
            @if ($editable ?? false)
                <textarea class="report-column-input" name="column_headers[{{ $key }}]" aria-label="{{ $label }} column heading" maxlength="120" rows="4" required @if ($isFinalized) readonly tabindex="-1" @endif>{{ old('column_headers.'.$key, $label) }}</textarea>
            @else
                {{ $label }}
            @endif
        </th>
    @endforeach
</tr>
