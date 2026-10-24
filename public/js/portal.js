(() => {
    const showPortalMessage = window.showPortalMessage;
    const { movePageFlashAlertsToToast, autoDismissPageInfoAlerts, cleanupBootstrapOverlays, cleanupStaleBootstrapBackdrops } = window.portalFeedback;
    const initPortalPollSections = window.initializePortalPolling;

    window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    if (window.jQuery && window.csrfToken) {
        window.jQuery.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
            },
        });
    }


    const applyPageSearch = () => window.initializeRecordSearch?.();

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
        window.initializeStudentAccountImports?.();
        window.initializeClassRosterImports?.();
        window.initializeAssessmentFilters?.();
        window.initializeClassSectionSelects?.();
        window.initializeClassForms?.();
        window.initializeReportsPage?.();
        window.initializeDashboardPage?.();
        applyPageSearch();
        autoDismissPageInfoAlerts();
    };

    initPortalPollSections();
    window.initializeTableTabs?.();
    window.initializeUserDirectory?.();
    window.initializeAssessmentFilters?.();
    window.initializeClassSectionSelects?.();
    window.initializeClassForms?.();
    window.initializeReportsPage?.();
    movePageFlashAlertsToToast();
    autoDismissPageInfoAlerts();

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

    const syncPageStyles = async (nextDocument, isCurrent = () => true) => {
        const previous = [...document.querySelectorAll('[data-page-style], style[data-ajax-page-style]')];
        const additions = [...nextDocument.head.querySelectorAll('style, link[data-page-style]')].map((node) => {
            const copy = node.cloneNode(true);
            delete copy.dataset.pageStyle;
            delete copy.dataset.ajaxPageStyle;
            copy.dataset.pendingPageStyle = '';
            return { node: copy, media: copy.media || '' };
        });
        const ready = additions.map(({ node }) => {
            node.media = 'not all';
            if (node.tagName !== 'LINK') return Promise.resolve();
            return new Promise((resolve, reject) => {
                node.onload = resolve;
                node.onerror = () => reject(new Error('The page stylesheet could not be loaded.'));
            });
        });

        additions.forEach(({ node }) => document.head.appendChild(node));
        try {
            await Promise.all(ready);
            if (! isCurrent()) {
                additions.forEach(({ node }) => node.remove());
                return false;
            }
            previous.forEach((node) => node.remove());
            additions.forEach(({ node, media }) => {
                delete node.dataset.pendingPageStyle;
                node.dataset.pageStyle = '';
                node.media = media;
            });
            return true;
        } catch (error) {
            additions.forEach(({ node }) => node.remove());
            throw error;
        }
    };

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
            const stylesReady = await syncPageStyles(nextDocument, () => requestId === latestPortalPageRequest);
            if (stylesReady === false || requestId !== latestPortalPageRequest) return true;
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

                    initPortalPollSections();
            window.initializeTableTabs?.();
            window.initializeUserDirectory?.();
            window.initializeDesignationPickers?.();
            window.initializeStudentAccountImports?.();
            window.initializeClassRosterImports?.();
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

        if (field.id === 'auditModule' && field.form?.id === 'auditFilterForm') {
            const action = field.form.querySelector('[name="action"]');
            if (action) action.value = '';
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
})();
