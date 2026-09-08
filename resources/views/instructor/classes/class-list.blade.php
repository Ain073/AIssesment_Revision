@if (! $instructorProfile || $subjects->isEmpty())
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        @if (! $instructorProfile)
            This account does not have an instructor profile yet, so class creation is temporarily unavailable.
        @else
            No active subjects are available yet. Add subjects first from the Department Chair portal before creating classes.
        @endif
    </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div class="table-switch-tabs mb-0">
        <button class="btn btn-outline-primary class-list-tab table-switch-button {{ $activeClassTab === 'active' ? 'active' : '' }} d-inline-flex align-items-center gap-2" data-table-tab-button="active" type="button" aria-pressed="{{ $activeClassTab === 'active' ? 'true' : 'false' }}">
            <span class="material-symbols-outlined fs-5">school</span>
            Active Classes
            <span class="table-switch-count">{{ $activeClassesCount }}</span>
        </button>
        <button class="btn btn-outline-primary class-list-tab table-switch-button {{ $activeClassTab === 'archived' ? 'active' : '' }} d-inline-flex align-items-center gap-2" data-table-tab-button="archived" type="button" aria-pressed="{{ $activeClassTab === 'archived' ? 'true' : 'false' }}">
            <span class="material-symbols-outlined fs-5">inventory_2</span>
            Archived Classes
            <span class="table-switch-count">{{ $archivedClassesCount }}</span>
        </button>
    </div>

    <button class="btn btn-psu d-flex align-items-center gap-2 {{ $activeClassTab === 'active' ? '' : 'd-none' }}" data-table-tab-panel="active" data-bs-target="#classModal" data-bs-toggle="modal" type="button" @disabled(! $instructorProfile || $subjects->isEmpty()) @if ($activeClassTab !== 'active') hidden @endif>
        <span class="material-symbols-outlined fs-5">add</span>
        Class
    </button>
</div>

@include('instructor.classes.class-table-panel', [
    'tabKey' => 'active',
    'heading' => 'Classes List',
    'emptyHeading' => 'No classes yet',
    'panelClasses' => $activeClasses,
    'isActivePanel' => $activeClassTab === 'active',
])

@include('instructor.classes.class-table-panel', [
    'tabKey' => 'archived',
    'heading' => 'Archived Classes',
    'emptyHeading' => 'No archived classes yet',
    'panelClasses' => $archivedClasses,
    'isActivePanel' => $activeClassTab === 'archived',
])

@include('instructor.classes.class-action-popups', ['classes' => $activeClasses])
