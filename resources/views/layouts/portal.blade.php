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
<body class="@yield('body_class')">
    @php
        $sidebarUser = auth()->user();
        $sidebarPhotoUrl = $sidebarUser?->profile_photo_path
            ? asset('storage/'.$sidebarUser->profile_photo_path)
            : null;
        $roleSwitches = collect($viewSwitches ?? []);
        $currentRoleSwitch = $roleSwitches->firstWhere('active', true);
        $availableRoleSwitches = $roleSwitches
            ->filter(fn (array $switch): bool => empty($switch['active']))
            ->values();
        $roleSwitchTarget = null;

        if ($currentRoleSwitch && ($currentRoleSwitch['label'] ?? '') === 'Instructor') {
            $roleSwitchTarget = $availableRoleSwitches->firstWhere('label', 'Dept Chair')
                ?? $availableRoleSwitches->firstWhere('label', 'Admin/Dean')
                ?? $availableRoleSwitches->first();
        } else {
            $roleSwitchTarget = $availableRoleSwitches->firstWhere('label', 'Instructor')
                ?? $availableRoleSwitches->first();
        }
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
                    <div class="sidebar-profile-photo d-flex align-items-center justify-content-center fw-bold">
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
                    <a class="profile-menu-link" href="{{ route('profile.show') }}">
                        <span class="material-symbols-outlined">account_circle</span>
                        <span class="sidebar-text">Profile</span>
                    </a>
                    @if ($roleSwitchTarget)
                        <button class="profile-menu-link" data-bs-target="#roleSwitchModal" data-bs-toggle="modal" type="button">
                            <span class="material-symbols-outlined">{{ $roleSwitchTarget['icon'] }}</span>
                            <span class="sidebar-text">{{ $roleSwitchTarget['label'] }}</span>
                            <span class="material-symbols-outlined ms-auto">sync_alt</span>
                        </button>
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

    @if ($roleSwitchTarget)
        <div class="modal fade" id="roleSwitchModal" tabindex="-1" aria-labelledby="roleSwitchModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h4" id="roleSwitchModalLabel">Switch to {{ $roleSwitchTarget['label'] }}</h3>
                        <button class="btn-close btn-close-white" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if ($currentRoleSwitch)
                            <div class="mb-3">
                                <p class="small fw-bold text-uppercase text-secondary mb-2">Current Role</p>
                                <div class="role-switch-current">
                                    <span class="material-symbols-outlined">{{ $currentRoleSwitch['icon'] }}</span>
                                    <span>{{ $currentRoleSwitch['label'] }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="role-switch-card">
                            <p class="small fw-bold text-uppercase text-secondary mb-2">Switch To</p>
                            <div class="role-switch-target">
                                <span class="material-symbols-outlined">{{ $roleSwitchTarget['icon'] }}</span>
                                <span>{{ $roleSwitchTarget['label'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button
                            class="btn btn-psu px-4 d-inline-flex align-items-center gap-2"
                            data-role-switch-confirm
                            data-role-href="{{ $roleSwitchTarget['href'] }}"
                            type="button"
                        >
                            <span class="material-symbols-outlined fs-5">switch_account</span>
                            Switch Role
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <header class="topbar d-flex align-items-center justify-content-between px-4">
        <div class="d-flex align-items-center gap-4">
            <h2 class="brand-text h4 fw-semibold mb-0" style="color: var(--psu-navy);">@yield('header')</h2>
        </div>

        <div class="d-flex align-items-center gap-3">
            @yield('topbar-actions')
            @if (! empty($showTopbarSearch))
                <div class="global-search">
                    <button class="btn btn-link text-secondary p-1 mobile-search-toggle d-lg-none" data-mobile-search-toggle type="button" aria-expanded="false" aria-controls="topbarSearchPanel" aria-label="Search">
                        <span class="material-symbols-outlined">search</span>
                    </button>
                    <div class="global-search-panel" id="topbarSearchPanel">
                    <div class="input-group global-search-input">
                        <input class="form-control" data-page-search data-global-search-url="{{ route('portal.search') }}" placeholder="{{ $topbarSearchPlaceholder ?? 'Search records...' }}" type="search" aria-label="Search records">
                        <span class="input-group-text bg-white"><span class="material-symbols-outlined fs-6">search</span></span>
                    </div>
                    <div class="global-search-results d-none" data-global-search-results></div>
                    </div>
                </div>
            @endif
            <div class="dropdown">
                <button class="btn btn-link text-secondary p-1 notification-button" data-bs-toggle="dropdown" type="button" aria-expanded="false" aria-label="Notifications">
                    <span class="material-symbols-outlined">notifications</span>
                    @if (($portalUnreadNotifications ?? 0) > 0)
                        <span class="notification-count">{{ $portalUnreadNotifications > 9 ? '9+' : $portalUnreadNotifications }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 notification-menu">
                    <div class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">
                        <span class="fw-bold" style="color: var(--psu-navy);">Notifications</span>
                        @if (($portalUnreadNotifications ?? 0) > 0)
                            <form action="{{ route('notifications.read-all') }}" method="POST">
                                @csrf
                                <button class="btn btn-link btn-sm p-0 text-decoration-none" type="submit">Mark all read</button>
                            </form>
                        @endif
                    </div>

                    @forelse (($portalNotifications ?? collect()) as $notification)
                        <form action="{{ route('notifications.read', $notification) }}" method="POST">
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
                </div>
            </div>
        </div>
    </header>

    <div class="portal-toast-stack" data-portal-toast-stack></div>

    <main class="main-content">
        <div class="page-container mx-auto">
            @yield('content')
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        if (window.jQuery && window.csrfToken) {
            window.jQuery.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken,
                },
            });
        }

        window.portalPollSections = [];

        const showPortalMessage = (message, type = 'success') => {
            const toastStack = document.querySelector('[data-portal-toast-stack]');

            if (! toastStack || ! message) {
                return;
            }

            const icons = {
                success: 'check_circle',
                warning: 'error',
                danger: 'cancel',
            };

            const toast = document.createElement('div');
            toast.className = `portal-toast portal-toast-${type}`;
            toast.setAttribute('role', 'status');

            const icon = document.createElement('span');
            icon.className = 'portal-toast-icon material-symbols-outlined';
            icon.textContent = icons[type] || 'info';

            const text = document.createElement('div');
            text.className = 'portal-toast-message';
            text.textContent = message;

            const closeButton = document.createElement('button');
            closeButton.className = 'portal-toast-close';
            closeButton.type = 'button';
            closeButton.setAttribute('aria-label', 'Close message');
            closeButton.innerHTML = '<span class="material-symbols-outlined fs-5">close</span>';

            let removeTimer = null;
            const removeToast = () => {
                window.clearTimeout(removeTimer);
                toast.classList.add('portal-toast-hiding');
                window.setTimeout(() => toast.remove(), 230);
            };

            closeButton.addEventListener('click', removeToast);

            toast.append(icon, text, closeButton);
            toastStack.append(toast);

            removeTimer = window.setTimeout(removeToast, 4500);
        };

        const movePageFlashAlertsToToast = () => {
            const flashAlerts = document.querySelectorAll('.page-container > .alert-success, .page-container > .alert-warning');

            flashAlerts.forEach((alert) => {
                const message = alert.textContent.trim();

                if (message) {
                    const type = alert.classList.contains('alert-warning') ? 'warning' : 'success';
                    showPortalMessage(message, type);
                }

                alert.remove();
            });
        };

        const searchableItems = () => {
            return Array.from(document.querySelectorAll('.page-container tbody tr, .page-container .assessment-card, .page-container .class-card, .page-container .class-performance-card, .page-container .report-assessment-card'))
                .filter((item) => ! item.closest('.modal') && ! item.closest('[data-search-ignore]'));
        };

        const pageSearchText = (item) => {
            return (item.dataset.searchText || item.textContent || '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();
        };

        const isInsideVisibleTab = (item) => {
            const tabPane = item.closest('.tab-pane');

            return ! tabPane || tabPane.classList.contains('active');
        };

        const setPageSearchEmpty = (show) => {
            const pageContainer = document.querySelector('.page-container');

            if (! pageContainer) {
                return;
            }

            let emptyMessage = pageContainer.querySelector('[data-page-search-empty]');

            if (! emptyMessage) {
                emptyMessage = document.createElement('div');
                emptyMessage.dataset.pageSearchEmpty = 'true';
                emptyMessage.className = 'alert alert-info d-none';
                emptyMessage.textContent = 'No matching records found.';
                pageContainer.prepend(emptyMessage);
            }

            emptyMessage.classList.toggle('d-none', ! show);
        };

        const applyPageSearch = () => {
            const searchInput = document.querySelector('[data-page-search]');
            const query = (searchInput?.value || '').toLowerCase().trim();
            const items = searchableItems();

            if (! searchInput || items.length === 0) {
                setPageSearchEmpty(false);
                return;
            }

            let visibleCount = 0;

            items.forEach((item) => {
                const isVisible = query === '' || pageSearchText(item).includes(query);

                item.hidden = ! isVisible;

                if (isVisible) {
                    visibleCount += isInsideVisibleTab(item) ? 1 : 0;
                }
            });

            setPageSearchEmpty(query !== '' && visibleCount === 0);
        };

        const clearPageSearch = () => {
            searchableItems().forEach((item) => {
                item.hidden = false;
            });

            setPageSearchEmpty(false);
        };

        const searchIconForType = (type) => {
            const icons = {
                Assessment: 'assignment',
                Class: 'school',
                Program: 'school',
                Result: 'grading',
                Subject: 'menu_book',
                User: 'person',
            };

            return icons[type] || 'search';
        };

        const portalEscapeHtml = (value) => {
            const element = document.createElement('div');
            element.textContent = value ?? '';

            return element.innerHTML;
        };

        const globalSearchBox = () => document.querySelector('[data-global-search-results]');

        const closeMobileSearch = () => {
            document.querySelectorAll('.global-search.is-open').forEach((search) => {
                search.classList.remove('is-open');
                search.querySelector('[data-mobile-search-toggle]')?.setAttribute('aria-expanded', 'false');
            });
        };

        const hideGlobalSearchResults = () => {
            const box = globalSearchBox();

            if (box) {
                box.classList.add('d-none');
                box.innerHTML = '';
            }
        };

        const cleanupBootstrapOverlays = () => {
            document.querySelectorAll('.modal.show').forEach((modal) => {
                bootstrap.Modal.getInstance(modal)?.hide();
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.style.display = 'none';
            });

            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        };

        const cleanupStaleBootstrapBackdrops = () => {
            if (document.querySelector('.modal.show')) {
                return;
            }

            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        };

        const renderGlobalSearchResults = (results, query) => {
            const box = globalSearchBox();

            if (! box) {
                return;
            }

            if (query.length < 2) {
                hideGlobalSearchResults();
                return;
            }

            if (! results.length) {
                box.innerHTML = '<div class="global-search-empty">No matching records found.</div>';
                box.classList.remove('d-none');
                return;
            }

            box.innerHTML = results.map((result) => `
                <a class="global-search-item" href="${portalEscapeHtml(result.url)}">
                    <span class="global-search-icon">
                        <span class="material-symbols-outlined fs-5">${searchIconForType(result.type)}</span>
                    </span>
                    <span>
                        <span class="fw-bold d-block" style="color: var(--psu-navy);">${portalEscapeHtml(result.title)}</span>
                        <span class="small text-secondary d-block">${portalEscapeHtml(result.subtitle)}</span>
                        <span class="badge text-bg-light border rounded-1 mt-1">${portalEscapeHtml(result.type)}</span>
                    </span>
                </a>
            `).join('');
            box.classList.remove('d-none');
        };

        const showGlobalSearchMessage = (message) => {
            const box = globalSearchBox();

            if (! box) {
                return;
            }

            box.innerHTML = `<div class="global-search-empty">${portalEscapeHtml(message)}</div>`;
            box.classList.remove('d-none');
        };

        let globalSearchTimer = null;
        let globalSearchRequest = null;

        const runGlobalSearch = (input) => {
            const query = input.value.trim();
            const url = input.dataset.globalSearchUrl;

            window.clearTimeout(globalSearchTimer);

            if (! url || query.length < 2) {
                hideGlobalSearchResults();
                return;
            }

            showGlobalSearchMessage('Searching...');

            globalSearchTimer = window.setTimeout(async () => {
                if (globalSearchRequest) {
                    globalSearchRequest.abort();
                }

                globalSearchRequest = new AbortController();

                try {
                    const response = await fetch(`${url}?q=${encodeURIComponent(query)}`, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: globalSearchRequest.signal,
                    });

                    if (! response.ok) {
                        showGlobalSearchMessage('Search is temporarily unavailable.');
                        return;
                    }

                    const data = await response.json();
                    renderGlobalSearchResults(data.results || [], query);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        showGlobalSearchMessage('Search is temporarily unavailable.');
                    }
                }
            }, 250);
        };

        document.addEventListener('input', (event) => {
            if (event.target.matches('[data-page-search]')) {
                if (event.target.dataset.globalSearchUrl) {
                    clearPageSearch();
                } else {
                    applyPageSearch();
                }

                runGlobalSearch(event.target);
            }
        });

        document.addEventListener('click', (event) => {
            const searchToggle = event.target.closest('[data-mobile-search-toggle]');

            if (searchToggle) {
                const search = searchToggle.closest('.global-search');
                const input = search?.querySelector('[data-page-search]');
                const isOpen = search?.classList.toggle('is-open');

                searchToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

                if (isOpen) {
                    window.setTimeout(() => input?.focus(), 0);
                } else {
                    hideGlobalSearchResults();
                }

                return;
            }

            if (! event.target.closest('.global-search')) {
                closeMobileSearch();
                hideGlobalSearchResults();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMobileSearch();
                hideGlobalSearchResults();
            }
        });

        document.addEventListener('shown.bs.tab', () => {
            applyPageSearch();
        });

        const formErrorMessage = async (response) => {
            try {
                const data = await response.json();

                if (data?.errors) {
                    return Object.values(data.errors).flat().join(' ');
                }

                return data?.message || 'Please check the form and try again.';
            } catch (error) {
                return 'Please check the form and try again.';
            }
        };

        const initPortalPollSections = (root = document) => {
            root.querySelectorAll('[data-poll-url]').forEach((section) => {
                if (section.dataset.pollReady === 'true') {
                    return;
                }

                section.dataset.pollReady = 'true';
                const pollUrl = section.dataset.pollUrl;
                const interval = Number(section.dataset.pollInterval || 5000);

                if (! pollUrl || interval < 1000) {
                    return;
                }

                let isLoading = false;

                const isUserTypingInside = () => {
                    const activeElement = document.activeElement;

                    return section.contains(activeElement)
                        && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeElement?.tagName);
                };

                const refreshSection = async (force = false) => {
                    if (! section.isConnected) {
                        return;
                    }

                    if (document.querySelector('.modal.show')) {
                        return;
                    }

                    if (isLoading || document.hidden || isUserTypingInside() || section.querySelector('.modal.show')) {
                        if (! force) {
                            return;
                        }
                    }

                    if (isLoading) {
                        return;
                    }

                    isLoading = true;

                    try {
                        const response = await fetch(pollUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            cache: 'no-store',
                        });

                        if (response.ok) {
                            const html = await response.text();

                            if (html.trim()) {
                                cleanupStaleBootstrapBackdrops();
                                section.innerHTML = html;
                                applyPageSearch();
                            }
                        }
                    } catch (error) {
                        console.warn('Partial refresh failed.', error);
                    } finally {
                        isLoading = false;
                    }
                };

                window.portalPollSections.push(refreshSection);
                window.setInterval(refreshSection, interval);
            });
        };

        window.refreshPortalSections = () => {
            window.portalPollSections.forEach((refreshSection) => refreshSection(true));
        };

        initPortalPollSections();
        movePageFlashAlertsToToast();

        window.addEventListener('storage', (event) => {
            if (event.key === 'portal-refresh-sections') {
                window.refreshPortalSections?.();
            }
        });

        window.addEventListener('focus', () => {
            window.refreshPortalSections?.();
        });

        document.addEventListener('click', function (event) {
            const copyButton = event.target.closest('[data-copy-target]');

            if (! copyButton || ! navigator.clipboard) {
                return;
            }

            const input = document.getElementById(copyButton.dataset.copyTarget);

            if (! input) {
                return;
            }

            navigator.clipboard.writeText(input.value).then(() => {
                const label = copyButton.querySelector('.copy-label');

                if (label) {
                    label.textContent = 'Copied';
                }
            });
        });

        const shouldUseAjaxPage = (event, link) => {
            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
                || link.target
                || link.hasAttribute('download')
                || link.dataset.noAjax === 'true'
                || link.dataset.bsToggle
                || link.classList.contains('disabled')
                || link.getAttribute('aria-disabled') === 'true'
                || link.closest('.modal')
            ) {
                return false;
            }

            const ajaxPageSelectors = [
                '.sidebar-link',
                '.hero-action-link',
                '.status-pill',
                '.class-tablink',
                '.class-list-tab',
                '.assessment-tab-button',
                '.results-class-filter-link',
                '.portal-ajax-link',
            ].join(', ');

            const url = new URL(link.href, window.location.href);

            return url.origin === window.location.origin
                && url.href !== window.location.href
                && ! url.hash
                && link.matches(ajaxPageSelectors);
        };

        const syncPageStyles = (nextDocument) => {
            document.querySelectorAll('style[data-ajax-page-style]').forEach((style) => style.remove());

            nextDocument.head.querySelectorAll('style').forEach((style) => {
                const nextStyle = document.createElement('style');
                nextStyle.dataset.ajaxPageStyle = 'true';
                nextStyle.textContent = style.textContent;
                document.head.appendChild(nextStyle);
            });
        };

        const syncReportSelection = (type) => {
            const checkboxes = Array.from(document.querySelectorAll(`[data-report-checkbox="${type}"]`));
            const checked = checkboxes.filter((checkbox) => checkbox.checked);
            const countTarget = document.querySelector(`[data-report-selected-count="${type}"]`);
            const submitButton = document.querySelector(`[data-report-submit="${type}"]`);
            const selectAll = document.querySelector(`[data-report-select-all="${type}"]`);

            if (countTarget) {
                countTarget.textContent = `${checked.length} selected`;
            }

            if (submitButton) {
                submitButton.disabled = checked.length === 0;
            }

            if (selectAll) {
                selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
            }
        };

        window.initializeReportsPage = () => {
            ['formative', 'summative'].forEach(syncReportSelection);
        };

        const classChartPreferenceKey = 'instructor-class-chart-mode';

        const applyClassChartMode = (mode) => {
            const section = document.querySelector('[data-class-performance]');

            if (! section) {
                return;
            }

            section.dataset.chartMode = mode;
            section.querySelectorAll('[data-class-chart-mode]').forEach((button) => {
                const isActive = button.dataset.classChartMode === mode;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        };

        window.initializeDashboardPage = () => {
            let mode = 'bar';

            try {
                mode = localStorage.getItem(classChartPreferenceKey) === 'pie' ? 'pie' : 'bar';
            } catch (error) {
                mode = 'bar';
            }

            applyClassChartMode(mode);
        };

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-class-chart-mode]');

            if (! button) {
                return;
            }

            const mode = button.dataset.classChartMode === 'pie' ? 'pie' : 'bar';

            try {
                localStorage.setItem(classChartPreferenceKey, mode);
            } catch (error) {
                // The selector still works when browser storage is unavailable.
            }

            applyClassChartMode(mode);
        });

        window.initializeDashboardPage();

        const loadPortalPage = async (url, pushHistory = true) => {
            try {
                cleanupBootstrapOverlays();

                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    cache: 'no-store',
                });

                if (! response.ok) {
                    window.location.href = url;
                    return;
                }

                const html = await response.text();
                const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextContainer = nextDocument.querySelector('.page-container');
                const nextSidebar = nextDocument.querySelector('.sidebar');
                const nextTopbar = nextDocument.querySelector('.topbar');
                const currentContainer = document.querySelector('.page-container');
                const currentSidebar = document.querySelector('.sidebar');
                const currentTopbar = document.querySelector('.topbar');

                if (! nextContainer || ! currentContainer) {
                    window.location.href = url;
                    return;
                }

                syncPageStyles(nextDocument);
                document.body.className = nextDocument.body.className || '';
                currentContainer.innerHTML = nextContainer.innerHTML;

                if (nextSidebar && currentSidebar) {
                    currentSidebar.innerHTML = nextSidebar.innerHTML;
                }

                if (nextTopbar && currentTopbar) {
                    currentTopbar.innerHTML = nextTopbar.innerHTML;
                }

                document.title = nextDocument.title || document.title;
                window.portalPollSections = [];
                initPortalPollSections();
                window.initializeReportsPage?.();
                window.initializeDashboardPage?.();
                applyPageSearch();
                movePageFlashAlertsToToast();
                cleanupStaleBootstrapBackdrops();
                window.scrollTo(0, 0);

                if (pushHistory) {
                    window.history.pushState({ ajaxPage: true }, '', url);
                }
            } catch (error) {
                window.location.href = url;
            }
        };

        document.addEventListener('click', (event) => {
            const confirmButton = event.target.closest('[data-role-switch-confirm]');

            if (! confirmButton) {
                return;
            }

            const roleHref = confirmButton.dataset.roleHref || '';

            if (! roleHref) {
                return;
            }

            confirmButton.disabled = true;
            document.body.classList.add('portal-loading');
            window.location.href = roleHref;
        });

        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');

            if (! link || ! shouldUseAjaxPage(event, link)) {
                return;
            }

            event.preventDefault();
            loadPortalPage(link.href);
        });

        document.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-report-tab]');

            if (! tab) {
                return;
            }

            const type = tab.dataset.reportTab;

            document.querySelectorAll('[data-report-tab]').forEach((button) => {
                const isActive = button.dataset.reportTab === type;
                button.classList.toggle('active', isActive);
                button.classList.toggle('btn-psu', isActive);
                button.classList.toggle('btn-outline-primary', ! isActive);
            });

            document.querySelectorAll('[data-report-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.reportPanel !== type;
            });

            document.querySelectorAll('[data-report-type-input]').forEach((input) => {
                input.value = type;
            });

            syncReportSelection(type);
        });

        document.addEventListener('change', (event) => {
            const selectAll = event.target.closest('[data-report-select-all]');
            const checkbox = event.target.closest('[data-report-checkbox]');

            if (selectAll) {
                const type = selectAll.dataset.reportSelectAll;

                document.querySelectorAll(`[data-report-checkbox="${type}"]`).forEach((target) => {
                    target.checked = selectAll.checked;
                });

                syncReportSelection(type);
                return;
            }

            if (checkbox) {
                syncReportSelection(checkbox.dataset.reportCheckbox);
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('[data-report-select-form]');

            if (! form) {
                return;
            }

            const type = form.dataset.reportSelectForm;
            const checked = form.querySelectorAll(`[data-report-checkbox="${type}"]:checked`);

            if (checked.length === 0) {
                event.preventDefault();
                syncReportSelection(type);
            }
        });

        window.addEventListener('popstate', () => {
            loadPortalPage(window.location.href, false);
        });

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('form[data-ajax-form]');

            if (! form) {
                return;
            }

            event.preventDefault();

            const submitButtons = form.querySelectorAll('button[type="submit"]');
            const errorBox = form.querySelector('[data-ajax-errors]') || document.createElement('div');

            if (! errorBox.hasAttribute('data-ajax-errors')) {
                errorBox.setAttribute('data-ajax-errors', 'true');
                errorBox.className = 'alert alert-danger d-none';
                form.querySelector('.modal-body, .p-4, form')?.prepend(errorBox);
            }

            errorBox.classList.add('d-none');
            errorBox.textContent = '';
            submitButtons.forEach((button) => button.disabled = true);

            try {
                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    cache: 'no-store',
                });

                if (! response.ok) {
                    errorBox.textContent = await formErrorMessage(response);
                    errorBox.classList.remove('d-none');
                    return;
                }

                const contentType = response.headers.get('content-type') || '';
                const data = contentType.includes('application/json') ? await response.json() : {};
                const modalElement = form.closest('.modal');

                const finishSuccess = () => {
                    if (form.dataset.resetOnSuccess === 'true') {
                        form.reset();
                    }

                    if (form.dataset.removeTarget) {
                        document.querySelector(form.dataset.removeTarget)?.remove();
                    }

                    if (form.dataset.decrementTarget) {
                        const target = document.querySelector(form.dataset.decrementTarget);
                        const value = Number(target?.textContent || 0);

                        if (target && Number.isFinite(value)) {
                            target.textContent = String(Math.max(value - 1, 0));
                        }
                    }

                    localStorage.setItem('portal-refresh-sections', String(Date.now()));
                    window.refreshPortalSections?.();
                    showPortalMessage(data.message || 'Saved successfully.');
                    cleanupStaleBootstrapBackdrops();

                    if (form.dataset.reloadPageOnSuccess === 'true') {
                        loadPortalPage(window.location.href, false);
                        return;
                    }

                    if (form.dataset.redirectOnSuccess) {
                        loadPortalPage(form.dataset.redirectOnSuccess);
                    }
                };

                if (modalElement?.classList.contains('show')) {
                    modalElement.addEventListener('hidden.bs.modal', finishSuccess, { once: true });
                    bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                    window.setTimeout(cleanupStaleBootstrapBackdrops, 350);
                } else {
                    finishSuccess();
                }
            } catch (error) {
                errorBox.textContent = 'Request failed. Please try again.';
                errorBox.classList.remove('d-none');
            } finally {
                submitButtons.forEach((button) => button.disabled = false);
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
