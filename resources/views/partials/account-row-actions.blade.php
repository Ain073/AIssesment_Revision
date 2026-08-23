@php
    $deleteDisabled = (int) auth()->id() === (int) $accountUser->id;
@endphp

<div class="account-row-actions d-inline-flex flex-nowrap align-items-center justify-content-center gap-1">
    <button class="btn btn-sm btn-outline-primary action-icon-btn d-inline-flex align-items-center justify-content-center" data-bs-target="#viewAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button" title="View account" aria-label="View {{ $accountUser->displayName() }}">
        <span class="material-symbols-outlined fs-6">visibility</span>
    </button>
    <button class="btn btn-sm btn-outline-primary action-icon-btn d-inline-flex align-items-center justify-content-center" data-bs-target="#editAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button" title="Edit account" aria-label="Edit {{ $accountUser->displayName() }}">
        <span class="material-symbols-outlined fs-6">edit</span>
    </button>
    <button class="btn btn-sm btn-outline-danger action-icon-btn d-inline-flex align-items-center justify-content-center" data-bs-target="#deleteAccountModal{{ $accountUser->id }}" data-bs-toggle="modal" type="button" aria-label="Delete {{ $accountUser->displayName() }}" @disabled($deleteDisabled) title="{{ $deleteDisabled ? 'You cannot delete your own account.' : 'Delete account' }}">
        <span class="material-symbols-outlined fs-6">delete</span>
    </button>
</div>
