@php
    $dashboardViewUser = auth()->user()?->loadMissing('roles');
    $dashboardViews = collect();

    if ($dashboardViewUser?->hasRole('instructor') || $dashboardViewUser?->hasRole('department_chair')) {
        $dashboardViews->push([
            'label' => 'Instructor',
            'icon' => 'co_present',
            'href' => route('instructor.dashboard'),
            'active' => request()->routeIs('instructor.dashboard'),
        ]);
    }

    if ($dashboardViewUser?->hasRole('admin_dean')) {
        $dashboardViews->push([
            'label' => 'Dean',
            'icon' => 'supervisor_account',
            'href' => route('admin-dean.dashboard'),
            'active' => request()->routeIs('admin-dean.dashboard'),
        ]);
    }

    if ($dashboardViewUser?->hasRole('department_chair')) {
        $dashboardViews->push([
            'label' => 'Department Chair',
            'icon' => 'assignment_ind',
            'href' => route('department-chair.dashboard'),
            'active' => request()->routeIs('department-chair.dashboard'),
        ]);
    }
@endphp

@if ($dashboardViews->isNotEmpty())
    <nav class="dashboard-view-nav" aria-label="Dashboard views">
        @foreach ($dashboardViews as $dashboardView)
            <a
                class="dashboard-view-link {{ $dashboardView['active'] ? 'active' : '' }}"
                href="{{ $dashboardView['href'] }}"
                @if ($dashboardView['active']) aria-current="page" @endif
            >
                <span class="material-symbols-outlined">{{ $dashboardView['icon'] }}</span>
                <span class="dashboard-view-label">{{ $dashboardView['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif
