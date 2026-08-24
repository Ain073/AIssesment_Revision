@php
    $reportsViewUser = auth()->user()?->loadMissing('roles');
    $reportsViews = collect();

    if ($reportsViewUser?->hasRole('instructor') || $reportsViewUser?->hasRole('department_chair')) {
        $reportsViews->push([
            'label' => 'Instructor',
            'icon' => 'summarize',
            'href' => route('instructor.reports'),
            'active' => request()->routeIs('instructor.reports*'),
        ]);
    }

    if ($reportsViewUser?->hasRole('department_chair')) {
        $reportsViews->push([
            'label' => 'Department',
            'icon' => 'analytics',
            'href' => route('department-chair.reports'),
            'active' => request()->routeIs('department-chair.reports*'),
        ]);
    }
@endphp

@if ($reportsViews->isNotEmpty())
    <nav class="dashboard-view-nav" aria-label="Reports views">
        @foreach ($reportsViews as $reportsView)
            <a
                class="dashboard-view-link {{ $reportsView['active'] ? 'active' : '' }}"
                href="{{ $reportsView['href'] }}"
                @if ($reportsView['active']) aria-current="page" @endif
            >
                <span class="material-symbols-outlined">{{ $reportsView['icon'] }}</span>
                <span class="dashboard-view-label">{{ $reportsView['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif
