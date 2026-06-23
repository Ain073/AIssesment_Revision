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

    <style>
        :root {
            --psu-navy: #001a70;
            --psu-navy-2: #0927d8;
            --psu-gold: #ffda27;
            --psu-gold-soft: #fff5bf;
            --psu-bg: #f7f9ff;
            --psu-line: #d6ddf5;
            --psu-muted: #5f6780;
            --psu-text: #1a1f2c;
        }

        body {
            background: var(--psu-bg);
            color: var(--psu-text);
            font-family: "Inter", sans-serif;
            overflow-x: hidden;
        }

        h1, h2, h3, .brand-text {
            font-family: "Oswald", sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 280px;
            background:
                linear-gradient(118deg, rgba(5, 33, 171, 0.98) 0%, rgba(13, 49, 221, 0.96) 48%, rgba(226, 196, 48, 0.88) 100%),
                radial-gradient(circle at 100% 0%, rgba(255, 226, 76, 0.45) 0%, rgba(255, 226, 76, 0) 34%),
                linear-gradient(180deg, #021063 0%, #0828c9 58%, #d4b736 100%);
            box-shadow: 12px 0 28px rgba(0, 26, 112, 0.18);
            z-index: 1040;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-weight: 700;
            letter-spacing: 0.02em;
            transition: background-color 0.18s ease, color 0.18s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(2px);
        }

        .sidebar-link.active {
            border-left: 4px solid var(--psu-gold);
            padding-left: calc(1.25rem - 4px);
            box-shadow: inset 0 -1px 0 rgba(255, 218, 39, 0.12), inset 0 1px 0 rgba(255, 218, 39, 0.12);
        }

        .topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 280px;
            min-height: 64px;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid var(--psu-line);
            box-shadow: 0 8px 24px rgba(9, 39, 216, 0.05);
            z-index: 1030;
        }

        .main-content {
            margin-left: 280px;
            padding-top: 64px;
            min-height: 100vh;
        }

        .page-container {
            max-width: 1440px;
            padding: 1.5rem;
        }

        .surface-card {
            background: #fff;
            border: 1px solid var(--psu-line);
            border-radius: 0.5rem;
            box-shadow: 0 14px 28px rgba(0, 26, 112, 0.05);
        }

        .icon-tile {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.35rem;
            background: var(--psu-gold-soft);
            color: var(--psu-navy-2);
        }

        .btn-psu {
            background: var(--psu-navy-2);
            border-color: var(--psu-navy-2);
            color: #fff;
            font-weight: 700;
            box-shadow: 0 10px 18px rgba(9, 39, 216, 0.16);
        }

        .btn-psu:hover,
        .btn-psu:focus {
            background: var(--psu-navy);
            border-color: var(--psu-navy);
            color: #fff;
        }

        .mode-switcher {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.4rem;
            padding: 0.35rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 0.85rem;
        }

        .mode-switcher.mode-switcher-stacked {
            grid-template-columns: 1fr;
        }

        .mode-switcher.mode-switcher-stacked .mode-switch-link {
            justify-content: flex-start;
            padding-inline: 1rem;
        }

        .mode-switch-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-height: 40px;
            min-width: 0;
            padding: 0.65rem 0.8rem;
            border-radius: 0.7rem;
            color: rgba(255, 255, 255, 0.76);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
            white-space: nowrap;
        }

        .mode-switch-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .mode-switch-link.active {
            background: #fff;
            color: var(--psu-navy);
            border: 1px solid rgba(255, 218, 39, 0.8);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.14);
        }

        .mode-switch-link .material-symbols-outlined {
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .sidebar-mode-switcher {
            padding: 0 1rem 1rem;
        }

        .sidebar-mode-switcher .sidebar-text {
            display: inline;
        }

        .profile-menu-shell {
            position: relative;
            margin-top: auto;
            padding: 0.75rem;
        }

        .profile-menu-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            color: inherit;
            padding: 0;
            text-align: left;
        }

        .profile-menu-toggle:hover,
        .profile-menu-toggle:focus {
            background: rgba(255, 255, 255, 0.06);
        }

        .profile-menu-panel {
            position: absolute;
            left: calc(100% + 12px);
            bottom: 12px;
            width: 220px;
            padding: 0.75rem;
            background: #fff;
            border: 1px solid rgba(0, 17, 58, 0.12);
            border-radius: 0.6rem;
            box-shadow: 0 18px 40px rgba(0, 17, 58, 0.2);
            z-index: 1080;
        }

        .profile-menu-link {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid #d8e0fb;
            background: #f5f8ff;
            color: var(--psu-navy);
            text-decoration: none;
            font-weight: 600;
            border-radius: 0.4rem;
        }

        .profile-menu-link:hover,
        .profile-menu-link:focus {
            background: #edf2ff;
            color: var(--psu-navy);
        }

        .profile-menu-link.disabled {
            opacity: 0.55;
            pointer-events: none;
        }

        .profile-menu-form {
            margin: 0;
        }

        .profile-logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.75rem 0.85rem;
            border: 1px solid #f0c7cc;
            background: #fff5f6;
            color: #9f1d2a;
            font-weight: 700;
            border-radius: 0.4rem;
        }

        .profile-logout-btn:hover,
        .profile-logout-btn:focus {
            background: #ffe8eb;
            color: #9f1d2a;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                width: 86px;
            }

            .sidebar .sidebar-text,
            .sidebar .small,
            .sidebar .brand-text {
                display: none;
            }

            .topbar,
            .main-content {
                left: 86px;
                margin-left: 86px;
            }

            .mode-switcher {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @stack('styles')

    <style>
        .directory-header,
        .publish-header,
        .assessment-header,
        .modal-content > .modal-header {
            position: relative;
            overflow: hidden;
            color: #fff !important;
            background:
                linear-gradient(118deg, rgba(5, 33, 171, 0.98) 0%, rgba(13, 49, 221, 0.96) 54%, rgba(226, 196, 48, 0.9) 145%),
                radial-gradient(circle at 100% 0%, rgba(255, 226, 76, 0.46) 0%, rgba(255, 226, 76, 0) 36%),
                linear-gradient(180deg, #021063 0%, #0828c9 58%, #d4b736 100%) !important;
            border-bottom: 1px solid rgba(255, 218, 39, 0.32) !important;
            box-shadow: inset 0 -1px 0 rgba(255, 218, 39, 0.18);
        }

        .directory-header::after,
        .publish-header::after,
        .assessment-header::after,
        .modal-content > .modal-header::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0)),
                radial-gradient(circle at 92% 18%, rgba(255, 218, 39, 0.2), rgba(255, 218, 39, 0) 28%);
            pointer-events: none;
        }

        .directory-header > *,
        .publish-header > *,
        .assessment-header > *,
        .modal-content > .modal-header > * {
            position: relative;
            z-index: 1;
        }

        .directory-header h1,
        .directory-header h2,
        .directory-header h3,
        .publish-header h1,
        .publish-header h2,
        .publish-header h3,
        .assessment-header h1,
        .assessment-header h2,
        .assessment-header h3,
        .modal-content > .modal-header h1,
        .modal-content > .modal-header h2,
        .modal-content > .modal-header h3 {
            color: #fff;
            letter-spacing: 0.01em;
            text-shadow: 0 2px 8px rgba(0, 18, 79, 0.24);
        }
    </style>
</head>
<body class="@yield('body_class')">
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

        @if (! empty($viewSwitches))
            <div class="sidebar-mode-switcher">
                <div class="mode-switcher {{ count($viewSwitches) > 2 ? 'mode-switcher-stacked' : '' }}">
                    @foreach ($viewSwitches as $switch)
                        <a class="mode-switch-link {{ !empty($switch['active']) ? 'active' : '' }}" href="{{ $switch['href'] }}">
                            <span class="material-symbols-outlined">{{ $switch['icon'] }}</span>
                            <span class="sidebar-text">{{ $switch['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

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
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px; background: var(--psu-gold); color: var(--psu-navy);">
                        {{ $profileInitials ?? 'U' }}
                    </div>
                    <div class="sidebar-text">
                        <p class="fw-bold text-white mb-0">{{ $profileName ?? 'User' }}</p>
                        <p class="small text-white-50 text-uppercase mb-0">{{ $profileMeta ?? '' }}</p>
                    </div>
                </div>
            </button>
            <div class="collapse profile-menu-panel" id="sidebarProfileMenu">
                <div class="d-grid gap-2">
                    <a class="profile-menu-link disabled" href="#" aria-disabled="true">
                        <span class="material-symbols-outlined">settings</span>
                        <span class="sidebar-text">Settings</span>
                    </a>
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
            <h2 class="brand-text h4 fw-semibold mb-0" style="color: var(--psu-navy);">@yield('header')</h2>
        </div>

        <div class="d-flex align-items-center gap-3">
            @yield('topbar-actions')
            @if (! empty($showTopbarSearch))
                <div class="input-group d-none d-lg-flex" style="width: 320px;">
                    <input class="form-control" placeholder="{{ $topbarSearchPlaceholder ?? 'Search records...' }}" type="text">
                    <span class="input-group-text bg-white"><span class="material-symbols-outlined fs-6">search</span></span>
                </div>
            @endif
            <button class="btn btn-link text-secondary p-1" type="button"><span class="material-symbols-outlined">notifications</span></button>
            <button class="btn btn-link text-secondary p-1" type="button"><span class="material-symbols-outlined">help_outline</span></button>
        </div>
    </header>

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
            const pageContainer = document.querySelector('.page-container');

            if (! pageContainer || ! message) {
                return;
            }

            const alert = document.createElement('div');
            alert.className = `alert alert-${type} ajax-status-alert`;
            alert.textContent = message;
            pageContainer.prepend(alert);

            window.setTimeout(() => alert.remove(), 4000);
        };

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
                                section.innerHTML = html;
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
                '.mode-switch-link',
                '.quick-action-card',
                '.status-pill',
                '.class-tablink',
                '.class-list-tab',
                '.assessment-tab-button',
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
                document.body.classList.add('portal-loading');

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
                window.scrollTo(0, 0);

                if (pushHistory) {
                    window.history.pushState({ ajaxPage: true }, '', url);
                }
            } catch (error) {
                window.location.href = url;
            } finally {
                document.body.classList.remove('portal-loading');
            }
        };

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
                button.classList.toggle('btn-psu', isActive);
                button.classList.toggle('btn-outline-primary', ! isActive);
            });

            document.querySelectorAll('[data-report-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.reportPanel !== type;
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
