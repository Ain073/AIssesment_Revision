<div class="dashboard-actions" aria-label="Quick actions">
    @foreach ($quickActions as $action)
        <a class="dashboard-action" href="{{ $action['href'] }}">
            <span class="material-symbols-outlined fs-5">{{ $action['icon'] }}</span>
            {{ $action['label'] }}
        </a>
    @endforeach
</div>
