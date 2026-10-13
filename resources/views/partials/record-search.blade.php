@php
    $recordSearchPlaceholder = \App\Support\RecordSearch::placeholder(request()->route()?->getName());
    $recordSearchTerm = $recordSearchPlaceholder ? \App\Support\RecordSearch::term() : '';
@endphp
@if ($recordSearchPlaceholder)
    <form action="{{ url()->current() }}" method="GET" role="search" class="record-search-form {{ ($inToolbar ?? false) ? '' : 'mb-4' }}" data-record-search-form data-ajax-page-form data-preserve-page-state="true">
        @foreach (request()->only(['tab', 'type', 'department', 'department_id', 'program', 'program_id', 'class', 'teacher', 'subject']) as $key => $value)
            @if (is_scalar($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <label class="visually-hidden" for="featureRecordSearch">{{ rtrim($recordSearchPlaceholder, '.') }}</label>
        <div class="input-group">
            <div class="record-search-field">
                <input class="form-control" id="featureRecordSearch" type="search" name="search" value="{{ $recordSearchTerm }}" placeholder="{{ $recordSearchPlaceholder }}" maxlength="100" autocomplete="off" data-page-search data-record-search-input aria-describedby="recordSearchStatus">
                <button class="btn record-search-clear" type="button" data-record-search-clear aria-label="Clear search" title="Clear search" @if ($recordSearchTerm === '') hidden @endif>
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
            </div>
            <button class="btn btn-psu record-search-submit" type="submit" aria-label="Search records"><span class="material-symbols-outlined fs-5" aria-hidden="true">search</span></button>
        </div>
        <div class="record-search-status small text-secondary mt-2" id="recordSearchStatus" data-record-search-status role="status" aria-live="polite"></div>
    </form>
@endif
