@php
    $documentFields = [
        'document_code' => ['Form code', 'FM-AA-CIA-20a', 80],
        'document_revision' => ['Revision', 'Rev. 0', 40],
        'document_date' => ['Document date', '30-Oct-2024', 40],
    ];
@endphp
<div class="report-document-control">
    @foreach ($documentFields as $field => [$label, $placeholder, $maxLength])
        <div class="report-document-control-line">
            @if ($editable)
                <input aria-label="{{ $label }}" class="report-header-input report-document-control-input" maxlength="{{ $maxLength }}" name="{{ $field }}" placeholder="{{ $placeholder }}" type="text" value="{{ old($field, $reportMeta[$field] ?? '') }}" @if ($isFinalized) readonly tabindex="-1" @endif>
                <span class="report-print-text">{{ old($field, $reportMeta[$field] ?? '') }}</span>
            @else
                <span>{{ $reportMeta[$field] ?? '' }}</span>
            @endif
        </div>
    @endforeach
</div>
