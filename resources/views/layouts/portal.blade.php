<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <link href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}" rel="stylesheet">
    @if (request()->routeIs('super-admin.*'))
        <link href="{{ asset('css/super-admin.css') }}?v={{ filemtime(public_path('css/super-admin.css')) }}" rel="stylesheet">
    @endif

    @stack('styles')

</head>
<body class="@yield('body_class')" data-portal-login-url="{{ route('login') }}" data-password-setup-url="{{ route('password.setup') }}">
    @php
        $sidebarUser = auth()->user();
        $sidebarPhotoUrl = $sidebarUser?->profilePhotoUrl();
    @endphp

    <aside class="sidebar d-flex flex-column">
        <div class="p-4">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ asset('images/psu-logo-transparent.png') }}" alt="PSU Seal" style="width: 64px; height: 64px; object-fit: contain;">
                <div class="sidebar-text">
                    <h1 class="brand-text h3 fw-bold text-white mb-1">AIssessment</h1>
                    <p class="small fw-bold text-white-50 mb-0">{{ $portalSubtitle ?? '' }}</p>
                </div>
            </div>
        </div>

        <nav class="flex-grow mt-4">
            @foreach (($navItems ?? []) as $item)
                <a class="sidebar-link {{ !empty($item['active']) ? 'active' : '' }}" href="{{ $item['href'] }}">
                    <span class="material-symbols-outlined">{{ $item['icon'] }}</span>
                    <span class="sidebar-text">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="border-top border-white border-opacity-10 profile-menu-shell">
            <button class="profile-menu-toggle" data-bs-target="#sidebarProfileMenu" data-bs-toggle="collapse" type="button" aria-expanded="false" aria-controls="sidebarProfileMenu">
                <div class="d-flex align-items-center gap-3">
                    <div class="sidebar-profile-photo d-flex align-items-center justify-content-center fw-bold" data-profile-photo-display data-profile-photo-alt="{{ $profileName ?? 'User' }} profile picture">
                        @if ($sidebarPhotoUrl)
                            <img src="{{ $sidebarPhotoUrl }}" alt="{{ $profileName ?? 'User' }} profile picture">
                        @else
                            {{ $profileInitials ?? 'U' }}
                        @endif
                    </div>
                    <div class="sidebar-text">
                        <p class="fw-bold text-white mb-0">{{ $profileName ?? 'User' }}</p>
                        <p class="small text-white-50 text-uppercase mb-0">{{ $profileMeta ?? '' }}</p>
                    </div>
                </div>
            </button>
            <div class="collapse profile-menu-panel" id="sidebarProfileMenu">
                <div class="d-grid gap-2">
                    <a class="profile-menu-link {{ request()->routeIs('profile.show') ? 'active' : '' }}" href="{{ route('profile.show') }}">
                        <span class="material-symbols-outlined">account_circle</span>
                        <span class="sidebar-text">Profile</span>
                    </a>
                    @if (! auth()->user()?->hasRole('student'))
                        <a class="profile-menu-link {{ request()->routeIs('profile.activity') ? 'active' : '' }}" href="{{ route('profile.activity') }}">
                            <span class="material-symbols-outlined">history</span>
                            <span class="sidebar-text">Activity Log</span>
                        </a>
                    @endif
                    <form action="{{ route('logout') }}" class="profile-menu-form" method="POST">
                        @csrf
                        <button class="profile-logout-btn" type="submit">
                            <span class="material-symbols-outlined">logout</span>
                            <span class="sidebar-text">Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <header class="topbar d-flex align-items-center justify-content-between px-4">
        <div class="d-flex align-items-center gap-4">
            @hasSection('topbar-leading')
                @yield('topbar-leading')
            @else
                <h2 class="brand-text h4 fw-semibold mb-0" style="color: var(--psu-navy);">@yield('header')</h2>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="dropdown" data-notifications-root data-notifications-url="{{ route('notifications.index') }}">
                <button class="btn btn-link text-secondary p-1 notification-button" data-bs-toggle="dropdown" type="button" aria-expanded="false" aria-label="Notifications">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="notification-count" data-notification-count @if (($portalUnreadNotifications ?? 0) === 0) hidden @endif>{{ ($portalUnreadNotifications ?? 0) > 9 ? '9+' : ($portalUnreadNotifications ?? 0) }}</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 notification-menu" data-notification-menu>
                    @include('partials.notification-menu')
                </div>
            </div>
        </div>
    </header>

    <div class="portal-toast-stack d-print-none" data-portal-toast-stack></div>

    <main class="main-content">
        <div class="page-container mx-auto">
            @unless ($__env->hasSection('record-search-toolbar'))
                @include('partials.record-search')
            @endunless
            @yield('content')
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/portal-session.js') }}?v={{ filemtime(public_path('js/portal-session.js')) }}"></script>
    <script src="{{ asset('js/profile-photo.js') }}?v={{ filemtime(public_path('js/profile-photo.js')) }}"></script>
    <script src="{{ asset('js/report-autosave.js') }}?v={{ filemtime(public_path('js/report-autosave.js')) }}"></script>
    <script src="{{ asset('js/user-directory.js') }}?v={{ filemtime(public_path('js/user-directory.js')) }}"></script>
    <script src="{{ asset('js/user-availability.js') }}?v={{ filemtime(public_path('js/user-availability.js')) }}"></script>
    <script src="{{ asset('js/record-search.js') }}?v={{ filemtime(public_path('js/record-search.js')) }}"></script>
    <script src="{{ asset('js/designation-picker.js') }}?v={{ filemtime(public_path('js/designation-picker.js')) }}"></script>
    <script src="{{ asset('js/class-student-removal.js') }}?v={{ filemtime(public_path('js/class-student-removal.js')) }}"></script>
    <script src="{{ asset('js/class-roster-import.js') }}?v={{ filemtime(public_path('js/class-roster-import.js')) }}"></script>
    <script src="{{ asset('js/student-account-import.js') }}?v={{ filemtime(public_path('js/student-account-import.js')) }}"></script>
    <script src="{{ asset('js/portal-feedback.js') }}?v={{ filemtime(public_path('js/portal-feedback.js')) }}"></script>
    <script src="{{ asset('js/assessment-filters.js') }}?v={{ filemtime(public_path('js/assessment-filters.js')) }}"></script>
    <script src="{{ asset('js/class-forms.js') }}?v={{ filemtime(public_path('js/class-forms.js')) }}"></script>
    <script src="{{ asset('js/portal-tabs.js') }}?v={{ filemtime(public_path('js/portal-tabs.js')) }}"></script>
    <script src="{{ asset('js/portal-polling.js') }}?v={{ filemtime(public_path('js/portal-polling.js')) }}"></script>
    <script src="{{ asset('js/report-selection.js') }}?v={{ filemtime(public_path('js/report-selection.js')) }}"></script>
    <script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
    <script src="{{ asset('js/portal.js') }}?v={{ filemtime(public_path('js/portal.js')) }}"></script>
    <script src="{{ asset('js/portal-notifications.js') }}?v={{ filemtime(public_path('js/portal-notifications.js')) }}"></script>
    @stack('scripts')
</body>
</html>
