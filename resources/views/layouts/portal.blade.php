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
                const clone = alert.cloneNode(true);
                clone.querySelectorAll('.material-symbols-outlined, .btn-close, .close').forEach((el) => el.remove());
                const message = clone.textContent.trim();

                if (message) {
                    const type = alert.classList.contains('alert-warning') ? 'warning' : 'success';
                    showPortalMessage(message, type);
                }

                alert.remove();
            });
        };

        window.addEventListener('beforeprint', () => {
            document.querySelectorAll('.portal-toast').forEach((toast) => toast.remove());
        });

        const autoDismissPageInfoAlerts = () => {
            document.querySelectorAll('.page-container > .alert-primary:not([data-sticky-alert])').forEach((alert) => {
                if (alert.dataset.autoDismissReady === 'true') {
                    return;
                }

                alert.dataset.autoDismissReady = 'true';
                window.setTimeout(() => {
                    alert.classList.add('page-info-alert-hiding');
                    window.setTimeout(() => alert.remove(), 260);
                }, 3500);
            });
        };

        const applyPageSearch = () => window.initializeRecordSearch?.();

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

        document.addEventListener('input', (event) => {
            if (event.target.matches('input[name="school_year_start"]')) {
                syncSchoolYearEnd(event.target);
            }

            if (event.target.matches('input[name="school_year_end"]')) {
                cleanTwoDigitYear(event.target);
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

        const applyAjaxFragments = (fragments = {}) => {
            Object.entries(fragments).forEach(([selector, html]) => {
                const target = document.querySelector(selector);

                if (target) {
                    target.innerHTML = html;
                }
            });

            initPortalPollSections();
            window.initializeTableTabs?.();
            window.initializeUserDirectory?.();
            window.initializeDesignationPickers?.();
            window.initializeAssessmentFilters?.();
            window.initializeClassSectionSelects?.();
            window.initializeClassForms?.();
            window.initializeReportsPage?.();
            window.initializeDashboardPage?.();
            applyPageSearch();
            autoDismissPageInfoAlerts();
        };

        const syncAssessmentFilter = (root) => {
            if (! root) {
                return;
            }

            const selectedTab = root.dataset.tableTabsSelected || root.dataset.tableTabsDefault || 'draft';
            const label = root.querySelector('[data-assessment-filter-label]');
            const draftSelect = root.querySelector('[data-assessment-filter-select="draft"]');
            const publishedSelect = root.querySelector('[data-assessment-filter-select="published"]');
            const activeSelect = selectedTab === 'published' ? publishedSelect : draftSelect;
            const inactiveSelect = selectedTab === 'published' ? draftSelect : publishedSelect;
            const activePanel = root.querySelector(`[data-table-tab-panel="${selectedTab}"]`);
            const filterKey = selectedTab === 'published' ? 'classId' : 'subjectId';
            const selectedValue = activeSelect?.value || '';
            let visibleCards = 0;

            if (label) {
                label.textContent = selectedTab === 'published' ? 'Filter by Class' : 'Filter by Subject';
                label.setAttribute('for', activeSelect?.id || '');
            }

            activeSelect?.classList.remove('d-none');
            inactiveSelect?.classList.add('d-none');

            activePanel?.querySelectorAll('[data-assessment-filter-card]').forEach((card) => {
                const isVisible = selectedValue === '' || card.dataset[filterKey] === selectedValue;
                card.hidden = ! isVisible;

                if (isVisible) {
                    visibleCards += 1;
                }
            });

            root.querySelectorAll('[data-assessment-filter-empty]').forEach((emptyMessage) => {
                const isCurrentTab = emptyMessage.dataset.assessmentFilterEmpty === selectedTab;

                emptyMessage.classList.toggle('d-none', ! isCurrentTab || selectedValue === '' || visibleCards > 0);
            });
        };

        window.initializeAssessmentFilters = (root = document) => {
            const filterRoots = root.matches?.('[data-assessment-filter-root]')
                ? [root]
                : Array.from(root.querySelectorAll('[data-assessment-filter-root]'));

            filterRoots.forEach(syncAssessmentFilter);
        };

        const sectionNameOptions = (prefix, yearLevel) => {
            const cleanPrefix = (prefix || 'Section').trim();
            const cleanYear = (yearLevel || '').trim();
            const letters = ['A', 'B', 'C', 'D'];

            return letters.map((letter) => cleanYear ? `${cleanPrefix} ${cleanYear}-${letter}` : `${cleanPrefix} ${letter}`);
        };

        const syncClassSectionSelect = (sectionSelect) => {
            const form = sectionSelect.closest('form');
            const programSelect = form?.querySelector('select[name="program_id"]');
            const yearSelect = form?.querySelector('select[name="year_level"]');
            const hasProgram = Boolean(programSelect?.value);
            const selectedProgram = programSelect?.selectedOptions?.[0];
            const prefix = selectedProgram?.dataset.sectionPrefix || 'Section';
            const selectedValue = sectionSelect.value || sectionSelect.dataset.selectedSection || '';
            const options = sectionNameOptions(prefix, yearSelect?.value || '');

            sectionSelect.innerHTML = hasProgram
                ? '<option value="">Select section</option>'
                : '<option value="">Select program first</option>';

            if (! hasProgram) {
                return;
            }

            if (selectedValue && ! options.includes(selectedValue)) {
                options.unshift(selectedValue);
            }

            options.forEach((optionValue) => {
                const option = document.createElement('option');
                option.value = optionValue;
                option.textContent = optionValue;
                option.selected = optionValue === selectedValue;
                sectionSelect.append(option);
            });
        };

        window.initializeClassSectionSelects = (root = document) => {
            const selects = root.matches?.('[data-class-section-select]')
                ? [root]
                : Array.from(root.querySelectorAll('[data-class-section-select]'));

            selects.forEach(syncClassSectionSelect);
        };

        const syncClassFormAvailability = (form, resetValues = false) => {
            if (! form) {
                return;
            }

            const yearSelect = form.querySelector('select[name="year_level"]');
            const sectionSelect = form.querySelector('[data-class-section-select]');
            const schoolYearPair = form.querySelector('[data-school-year-pair]');
            const schoolYearInputs = schoolYearPair ? Array.from(schoolYearPair.querySelectorAll('input')) : [];

            if (yearSelect) {
                yearSelect.disabled = false;
            }

            if (sectionSelect) {
                sectionSelect.disabled = false;
                syncClassSectionSelect(sectionSelect);
            }

            schoolYearPair?.classList.remove('is-disabled');
            schoolYearInputs.forEach((input) => {
                input.disabled = false;
            });
        };

        window.initializeClassForms = (root = document) => {
            const forms = root.matches?.('form')
                ? [root]
                : Array.from(root.querySelectorAll('form'));

            forms
                .filter((form) => form.querySelector('[data-class-section-select]'))
                .forEach((form) => syncClassFormAvailability(form));
        };

        const cleanTwoDigitYear = (input) => {
            input.value = input.value.replace(/\D/g, '').slice(0, 2);
        };

        const syncSchoolYearEnd = (startInput) => {
            cleanTwoDigitYear(startInput);

            if (startInput.value.length !== 2) {
                return;
            }

            const pair = startInput.closest('[data-school-year-pair]');
            const endInput = pair?.querySelector('input[name="school_year_end"]');
            const nextYear = (Number(startInput.value) + 1) % 100;

            if (endInput) {
                endInput.value = String(nextYear).padStart(2, '0');
            }
        };

        const syncTableTabs = (root, selectedTab = null, updateUrl = false) => {
            const buttons = Array.from(root.querySelectorAll('[data-table-tab-button]'));
            const validTabs = buttons.map((button) => button.dataset.tableTabButton).filter(Boolean);

            if (validTabs.length === 0) {
                return;
            }

            const url = new URL(window.location.href);
            const param = root.dataset.tableTabsParam || 'tab';
            const fallbackTab = root.dataset.tableTabsDefault || validTabs[0];
            const storedTab = root.dataset.tableTabsSelected;
            const tab = validTabs.includes(selectedTab)
                ? selectedTab
                : (validTabs.includes(storedTab)
                    ? storedTab
                    : (validTabs.includes(url.searchParams.get(param)) ? url.searchParams.get(param) : fallbackTab));

            root.dataset.tableTabsSelected = tab;
            root.dataset.tableTabsDefault = tab;

            buttons.forEach((button) => {
                const isActive = button.dataset.tableTabButton === tab;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            root.querySelectorAll('[data-table-tab-panel]').forEach((panel) => {
                const isActive = panel.dataset.tableTabPanel === tab;
                panel.hidden = ! isActive;
                panel.classList.toggle('d-none', ! isActive);
            });

            if (updateUrl) {
                url.searchParams.set(param, tab);
                window.history.pushState({ ajaxPage: true }, '', url.href);
            }

            applyPageSearch();
            window.initializeAssessmentFilters?.(root);
        };

        window.initializeTableTabs = (root = document) => {
            const tabRoots = root.matches?.('[data-table-tabs-root]')
                ? [root]
                : Array.from(root.querySelectorAll('[data-table-tabs-root]'));

            tabRoots.forEach((tabRoot) => syncTableTabs(tabRoot));
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
                    if (window.portalAuthenticationRequired) {
                        window.clearInterval(pollTimer);
                        return;
                    }

                    if (! section.isConnected) {
                        window.clearInterval(pollTimer);
                        return;
                    }

                    const activeModal = document.querySelector('.modal.show');

                    if (activeModal && ! activeModal.contains(section)) {
                        return;
                    }

                    const hasOpenDropdown = Boolean(section.querySelector('.dropdown-menu.show'));

                    if (isLoading || document.hidden || isUserTypingInside() || section.querySelector('.modal.show') || hasOpenDropdown) {
                        if (! force) {
                            return;
                        }
                    }

                    if (isLoading) {
                        return;
                    }

                    isLoading = true;

                    try {
                        let targetUrl;
                        try {
                            targetUrl = new URL(pollUrl, window.location.origin);
                        } catch {
                            targetUrl = new URL(window.location.origin + pollUrl);
                        }

                        const tabParam = section.dataset.tableTabsParam || 'tab';
                        const currentTab = section.dataset.tableTabsSelected
                            || new URL(window.location.href).searchParams.get(tabParam)
                            || section.dataset.tableTabsDefault;

                        if (currentTab && ! targetUrl.searchParams.has(tabParam)) {
                            targetUrl.searchParams.set(tabParam, currentTab);
                        }
                        const searchTerm = new URL(window.location.href).searchParams.get('search') || '';
                        if (searchTerm) targetUrl.searchParams.set('search', searchTerm);

                        const response = await (window.portalFetch || fetch)(targetUrl.href, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            cache: 'no-store',
                        });

                        if (response.ok) {
                            const html = await response.text();
                            if (! section.isConnected || searchTerm !== (new URL(window.location.href).searchParams.get('search') || '')) return;

                            if (html.trim()) {
                                const currentScrollY = window.scrollY;
                                cleanupStaleBootstrapBackdrops();
                                section.innerHTML = html;
                                window.initializeTableTabs?.(section);
                                window.initializeAssessmentFilters?.(section);
                                window.initializeClassSectionSelects?.(section);
                                window.initializeClassForms?.(section);
                                applyPageSearch();
                                if (window.scrollY !== currentScrollY) {
                                    window.scrollTo(window.scrollX, currentScrollY);
                                }
                            }
                        }
                    } catch (error) {
                        console.warn('Partial refresh failed.', error);
                    } finally {
                        isLoading = false;
                    }
                };

                window.portalPollSections.push(refreshSection);
                const pollTimer = window.setInterval(refreshSection, interval);
            });
        };

        window.refreshPortalSections = () => {
            window.portalPollSections.forEach((refreshSection) => refreshSection(true));
        };

        initPortalPollSections();
        window.initializeTableTabs?.();
        window.initializeUserDirectory?.();
        window.initializeAssessmentFilters?.();
        window.initializeClassSectionSelects?.();
        window.initializeClassForms?.();
        movePageFlashAlertsToToast();
        autoDismissPageInfoAlerts();

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
                '.table-switch-button',
                '.dashboard-view-link',
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

        let latestPortalPageRequest = 0;
        const loadPortalPage = async (url, pushHistory = true, preserveState = false) => {
            const requestId = ++latestPortalPageRequest;
            const preserveCurrentPage = preserveState && new URL(url, window.location.href).pathname === window.location.pathname;
            const startingParams = new URL(window.location.href).searchParams;
            const scrollPosition = { x: window.scrollX, y: window.scrollY };
            const tableScrollPositions = preserveCurrentPage
                ? Array.from(document.querySelectorAll('.page-container .table-responsive'), (table) => ({ x: table.scrollLeft, y: table.scrollTop }))
                : [];

            try {
                if (window.beforeReportNavigation && ! await window.beforeReportNavigation()) {
                    return true;
                }
                if (requestId !== latestPortalPageRequest) return true;
                cleanupBootstrapOverlays();

                const response = await (window.portalFetch || fetch)(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    cache: 'no-store',
                });

                if (! response.ok) {
                    throw new Error('Page refresh failed.');
                }

                const html = await response.text();
                if (requestId !== latestPortalPageRequest) {
                    return true;
                }
                const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextContainer = nextDocument.querySelector('.page-container');
                const nextSidebar = nextDocument.querySelector('.sidebar');
                const nextTopbar = nextDocument.querySelector('.topbar');
                const currentContainer = document.querySelector('.page-container');
                const currentSidebar = document.querySelector('.sidebar');
                const currentTopbar = document.querySelector('.topbar');

                if (! nextContainer || ! currentContainer) {
                    throw new Error('Page content is unavailable.');
                }

                const currentSearchForm = preserveCurrentPage ? currentContainer.querySelector('[data-record-search-form]') : null;
                const currentSearchInput = currentSearchForm?.querySelector('[data-record-search-input]');
                const requestedUrl = new URL(url, window.location.href);
                if (currentSearchInput && currentSearchInput.value.trim() !== (requestedUrl.searchParams.get('search') || '').trim()) {
                    return true;
                }
                if (preserveCurrentPage) {
                    const currentParams = new URL(window.location.href).searchParams;
                    ['tab', 'type'].forEach((key) => {
                        if (currentParams.get(key) !== startingParams.get(key)) {
                            if (currentParams.has(key)) requestedUrl.searchParams.set(key, currentParams.get(key));
                            else requestedUrl.searchParams.delete(key);
                        }
                    });
                    url = requestedUrl.href;
                }
                const activeBootstrapTabs = preserveCurrentPage
                    ? Array.from(document.querySelectorAll('[data-bs-toggle="tab"].active'), (button) => button.getAttribute('data-bs-target'))
                    : [];
                syncPageStyles(nextDocument);
                document.body.className = nextDocument.body.className || '';
                const focusedSearchInput = currentSearchForm?.querySelector('[data-record-search-input]') === document.activeElement
                    ? document.activeElement : null;
                const searchSelection = focusedSearchInput ? [focusedSearchInput.selectionStart, focusedSearchInput.selectionEnd] : null;
                window.cleanupProfilePhotoPreviews?.(currentContainer);
                currentContainer.innerHTML = nextContainer.innerHTML;
                const nextSearchInput = currentSearchInput ? currentContainer.querySelector('[data-record-search-input]') : null;
                if (nextSearchInput) nextSearchInput.replaceWith(currentSearchInput);

                if (! preserveCurrentPage && nextSidebar && currentSidebar) {
                    currentSidebar.innerHTML = nextSidebar.innerHTML;
                }

                if (! preserveCurrentPage && nextTopbar && currentTopbar) {
                    currentTopbar.innerHTML = nextTopbar.innerHTML;
                }

                window.refreshPortalNotifications?.();

                document.title = nextDocument.title || document.title;
                if (pushHistory) {
                    window.history[pushHistory === 'replace' ? 'replaceState' : 'pushState']({ ajaxPage: true }, '', url);
                }

                window.portalPollSections = [];
                initPortalPollSections();
                window.initializeTableTabs?.();
                window.initializeUserDirectory?.();
                window.initializeDesignationPickers?.();
                window.initializeAssessmentFilters?.();
                window.initializeClassSectionSelects?.();
                window.initializeClassForms?.();
                window.initializeReportsPage?.();
                window.initializeDashboardPage?.();
                activeBootstrapTabs.forEach((target) => {
                    const button = target ? document.querySelector(`[data-bs-toggle="tab"][data-bs-target="${target}"]`) : null;
                    if (button) bootstrap.Tab.getOrCreateInstance(button).show();
                });
                applyPageSearch();
                movePageFlashAlertsToToast();
                autoDismissPageInfoAlerts();
                cleanupStaleBootstrapBackdrops();
                if (preserveCurrentPage) {
                    if (focusedSearchInput) {
                        focusedSearchInput.focus({ preventScroll: true });
                        focusedSearchInput.setSelectionRange(...searchSelection);
                    }
                    document.querySelectorAll('.page-container .table-responsive').forEach((table, index) => {
                        const position = tableScrollPositions[index];
                        if (position) {
                            table.scrollLeft = position.x;
                            table.scrollTop = position.y;
                        }
                    });
                    window.scrollTo(scrollPosition.x, scrollPosition.y);
                } else {
                    window.scrollTo(0, 0);
                }
                return true;
            } catch (error) {
                if (requestId !== latestPortalPageRequest) {
                    return true;
                }
                if (! preserveCurrentPage && ! window.portalAuthenticationRequired) {
                    window.location.href = url;
                }
                return false;
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

        document.addEventListener('change', (event) => {
            const field = event.target.closest('[data-submit-on-change]');

            if (! field) {
                return;
            }

            field.form?.requestSubmit();
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-ajax-page-form]');

            if (! form || (form.method || 'GET').toUpperCase() !== 'GET') {
                return;
            }

            event.preventDefault();

            // Named controls such as the audit Action filter can shadow form.action.
            const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
            const params = new URLSearchParams();

            new FormData(form).forEach((value, key) => {
                const stringValue = String(value);

                if (stringValue !== '') {
                    params.append(key, stringValue);
                }
            });

            const currentParams = new URL(window.location.href).searchParams;
            if (form.matches('[data-record-search-form]') && form.id !== 'auditFilterForm') {
                ['tab', 'type', 'department', 'department_id', 'program', 'program_id', 'class', 'teacher', 'subject'].forEach((key) => {
                    if (currentParams.has(key)) params.set(key, currentParams.get(key));
                    else params.delete(key);
                });
            } else {
                ['search', 'tab', 'type'].forEach((key) => {
                    if (! form.querySelector(`[name="${key}"]`) && currentParams.has(key)) params.set(key, currentParams.get(key));
                });
            }

            url.search = params.toString();
            loadPortalPage(url.href, form.matches('[data-record-search-form]') ? 'replace' : true, form.dataset.preservePageState === 'true').then((loaded) => {
                if (! loaded && form.dataset.preservePageState === 'true') {
                    showPortalMessage('The list could not refresh. Please try again.', 'warning');
                    const status = form.querySelector('[data-record-search-status]');
                    if (status) status.textContent = 'Search could not complete. Please try again.';
                    form.removeAttribute('aria-busy');
                }
            });
        });

        document.addEventListener('click', (event) => {
            const tableTabButton = event.target.closest('[data-table-tab-button]');

            if (! tableTabButton) {
                return;
            }

            const root = tableTabButton.closest('[data-table-tabs-root]');

            if (! root) {
                return;
            }

            event.preventDefault();
            syncTableTabs(root, tableTabButton.dataset.tableTabButton, true);
        });

        document.addEventListener('change', (event) => {
            const select = event.target.closest('[data-assessment-filter-select]');

            if (! select) {
                return;
            }

            syncAssessmentFilter(select.closest('[data-assessment-filter-root]'));
            const url = new URL(window.location.href);
            const key = select.dataset.assessmentFilterSelect === 'published' ? 'class' : 'subject';
            if (select.value) url.searchParams.set(key, select.value);
            else url.searchParams.delete(key);
            window.history.replaceState({ ajaxPage: true }, '', url.href);
            applyPageSearch();
        });

        document.addEventListener('change', (event) => {
            const field = event.target.closest('select[name="subject_id"], select[name="program_id"], select[name="year_level"]');

            if (! field) {
                return;
            }

            const form = field.closest('form');

            form?.querySelectorAll('[data-class-section-select]').forEach((sectionSelect) => {
                sectionSelect.dataset.selectedSection = '';
                sectionSelect.value = '';
            });

            syncClassFormAvailability(form, field.name === 'subject_id' && ! field.value);
        });

        document.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-report-tab]');

            if (! tab) {
                return;
            }

            const type = tab.dataset.reportTab;
            const url = new URL(window.location.href);
            url.searchParams.set('type', type);
            window.history.replaceState({ ajaxPage: true }, '', url.href);

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
            applyPageSearch();
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

            if (form.dataset.ajaxSubmitting === 'true') {
                return;
            }
            form.dataset.ajaxSubmitting = 'true';

            const submitButtons = form.querySelectorAll('button[type="submit"]');
            const buttonStates = Array.from(submitButtons, (button) => ({ button, disabled: button.disabled, html: button.innerHTML }));
            const errorBox = form.querySelector('[data-ajax-errors]') || document.createElement('div');

            if (! errorBox.hasAttribute('data-ajax-errors')) {
                errorBox.setAttribute('data-ajax-errors', 'true');
                errorBox.className = 'alert alert-danger d-none';
                (form.querySelector('.modal-body, .p-4') || form).prepend(errorBox);
            }

            errorBox.classList.add('d-none');
            errorBox.textContent = '';
            submitButtons.forEach((button) => {
                button.disabled = true;
                if (form.dataset.submittingText) button.textContent = form.dataset.submittingText;
            });
            let saved = false;

            try {
                const actionUrl = new URL(form.action, window.location.href);
                const searchTerm = new URL(window.location.href).searchParams.get('search');
                if (searchTerm) actionUrl.searchParams.set('search', searchTerm);
                const response = await (window.portalFetch || fetch)(actionUrl.href, {
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
                if (form.dataset.preservePageState === 'true' && ! contentType.includes('application/json')) {
                    errorBox.textContent = 'Your session may have expired. Refresh the page and sign in again.';
                    errorBox.classList.remove('d-none');
                    return;
                }
                const data = contentType.includes('application/json') ? await response.json() : {};
                saved = true;
                const modalElement = form.closest('.modal');

                const finishSuccess = async () => {
                    if (form.dataset.resetOnSuccess === 'true') {
                        form.reset();
                        window.initializeClassForms?.(form);
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

                    if (data.fragments && typeof data.fragments === 'object') {
                        applyAjaxFragments(data.fragments);
                    }

                    if (data.url && ! form.dataset.redirectOnSuccess && form.dataset.reloadPageOnSuccess !== 'true') {
                        const nextUrl = new URL(data.url, window.location.href);
                        if (searchTerm) nextUrl.searchParams.set('search', searchTerm);
                        window.history.replaceState({ ajaxPage: true }, '', nextUrl.href);
                    }

                    try {
                        localStorage.setItem('portal-refresh-sections', String(Date.now()));
                    } catch (error) {
                        // Saving still succeeds when browser storage is unavailable.
                    }
                    window.refreshPortalSections?.();
                    showPortalMessage(data.message || 'Saved successfully.');
                    if (data.warning) showPortalMessage(data.warning, 'warning');
                    cleanupStaleBootstrapBackdrops();

                    if (form.dataset.reloadPageOnSuccess === 'true') {
                        const loaded = await loadPortalPage(window.location.href, false, form.dataset.preservePageState === 'true');
                        if (! loaded && form.dataset.preservePageState === 'true') {
                            showPortalMessage('Changes saved, but the list could not refresh. Refresh the page to see the updated details.', 'warning');
                        }
                        return;
                    }

                    if (form.dataset.redirectOnSuccess) {
                        await loadPortalPage(form.dataset.redirectOnSuccess);
                    }
                };

                if (modalElement?.classList.contains('show')) {
                    await new Promise((resolve) => {
                        modalElement.addEventListener('hidden.bs.modal', resolve, { once: true });
                        bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                    });
                }
                await finishSuccess();
            } catch (error) {
                if (saved) {
                    showPortalMessage('Changes saved, but the page could not refresh. Refresh the page to see the updated details.', 'warning');
                } else {
                    errorBox.textContent = 'Request failed. Please try again.';
                    errorBox.classList.remove('d-none');
                }
            } finally {
                delete form.dataset.ajaxSubmitting;
                buttonStates.forEach(({ button, disabled, html }) => {
                    button.disabled = disabled;
                    button.innerHTML = html;
                });
            }
        });
        window.navigatePortalPage = loadPortalPage;
        window.showPortalMessage = showPortalMessage;
    </script>
    <script src="{{ asset('js/portal-notifications.js') }}?v={{ filemtime(public_path('js/portal-notifications.js')) }}"></script>
    @stack('scripts')
</body>
</html>
