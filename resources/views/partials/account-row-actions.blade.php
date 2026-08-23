@php
    $deleteDisabled = (int) auth()->id() === (int) $accountUser->id;
@endphp

<div class="d-flex flex-wrap justify-content-center gap-1 account-row-actions">
    <button class="btn btn-sm record-action-trigger" data-bs-target="#viewAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button" aria-label="View {{ $accountUser->displayName() }}">
        <span class="material-symbols-outlined fs-6">visibility</span>
        View
    </button>
    <button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" data-bs-target="#editAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button" aria-label="Edit {{ $accountUser->displayName() }}">
        <span class="material-symbols-outlined fs-6">edit</span>
        Edit
    </button>
    <button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" data-bs-target="#deleteAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button" aria-label="Delete {{ $accountUser->displayName() }}" @disabled($deleteDisabled) title="{{ $deleteDisabled ? 'You cannot delete your own account.' : 'Delete account' }}">
        <span class="material-symbols-outlined fs-6">delete</span>
        Delete
    </button>
</div>
