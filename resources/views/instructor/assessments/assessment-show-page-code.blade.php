@php
    $pageConfig = [
        'typeLabels' => $itemTypes,
        'oldItems' => $oldItems,
        'validationErrors' => $errors->getMessages(),
        'firstQuestionNumber' => ((int) $assessment->items->max('sort_order')) + 1,
        'openOnError' => $errors->any() && ! $detailsHasErrors,
    ];
@endphp

<script id="assessmentBuilderConfig" type="application/json">@json($pageConfig)</script>
<script src="{{ asset('js/assessment-builder.js') }}?v={{ filemtime(public_path('js/assessment-builder.js')) }}"></script>
