<div class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">
    <span class="fw-bold" style="color: var(--psu-navy);">Notifications</span>
    @if (($portalUnreadNotifications ?? 0) > 0)
        <form action="{{ route('notifications.read-all') }}" method="POST" data-notification-form>
            @csrf
            <button class="btn btn-link btn-sm p-0 text-decoration-none" type="submit">Mark all read</button>
        </form>
    @endif
</div>

@forelse (($portalNotifications ?? collect()) as $notification)
    <form action="{{ route('notifications.read', $notification) }}" method="POST" data-notification-form>
        @csrf
        <button class="notification-item {{ $notification->isUnread() ? 'unread' : '' }}" type="submit">
            <div class="d-flex justify-content-between gap-2">
                <span class="fw-bold" style="color: var(--psu-navy);">{{ $notification->title }}</span>
                <span class="small text-secondary text-nowrap">{{ $notification->created_at->diffForHumans(null, true) }}</span>
            </div>
            <p class="small text-secondary mb-0 mt-1">{{ $notification->message }}</p>
        </button>
    </form>
@empty
    <div class="p-4 text-center text-secondary">
        <span class="material-symbols-outlined d-block mb-2">notifications</span>
        No notifications yet.
    </div>
@endforelse
