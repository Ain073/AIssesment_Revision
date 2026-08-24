@if (! empty($userDirectoryTabs))
    <div class="table-switch-tabs mb-4" role="navigation" aria-label="User directory views">
        @foreach ($userDirectoryTabs as $tab)
            <a class="btn btn-outline-primary table-switch-button {{ ! empty($tab['active']) ? 'active' : '' }} d-inline-flex align-items-center gap-2"
                href="{{ $tab['href'] }}"
                @if (! empty($tab['active'])) aria-current="page" @endif
            >
                <span class="material-symbols-outlined fs-5">{{ $tab['icon'] }}</span>
                {{ $tab['label'] }}
                <span class="table-switch-count">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>
@endif
