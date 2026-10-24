(() => {
    window.portalPollSections = [];

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
                            window.portalFeedback.cleanupStaleBootstrapBackdrops();
                            section.innerHTML = html;
                            window.initializeTableTabs?.(section.closest('[data-table-tabs-root]') || section);
                            window.initializeAssessmentFilters?.(section);
                            window.initializeClassSectionSelects?.(section);
                            window.initializeClassForms?.(section);
                            window.initializeRecordSearch?.();
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

    window.addEventListener('storage', (event) => {
        if (event.key === 'portal-refresh-sections') {
            window.refreshPortalSections?.();
        }
    });

    window.addEventListener('focus', () => {
        window.refreshPortalSections?.();
    });


    window.initializePortalPolling = initPortalPollSections;
})();
