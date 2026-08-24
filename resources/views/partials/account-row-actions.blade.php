<div class="account-row-actions d-inline-flex flex-nowrap align-items-center justify-content-center gap-1">
    <button class="btn btn-sm record-action-trigger" data-bs-target="#viewAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button" aria-label="View {{ $accountUser->displayName() }}">
        <span class="material-symbols-outlined fs-6">visibility</span>
        View
    </button>
</div>
