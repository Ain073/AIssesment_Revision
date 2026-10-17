(() => {
    const interval = 30000;
    let timer;
    let polling;
    let pollRoot;
    let version = 0;
    let submitting = false;
    let stopped = false;
    const root = () => document.querySelector('[data-notifications-root]');

    const pause = () => {
        window.clearTimeout(timer);
        version++;
        polling?.abort();
        polling = null;
    };

    const schedule = () => {
        window.clearTimeout(timer);
        if (! document.hidden && ! stopped && ! submitting && ! window.portalAuthenticationRequired && root()) {
            timer = window.setTimeout(refresh, interval);
        }
    };

    const readResponse = async (response) => {
        const isJson = (response.headers.get('content-type') || '').includes('application/json');
        const data = isJson ? await response.json() : {};
        if ([401, 419].includes(response.status) || ['account_inactive', 'password_change_required'].includes(data.code) || (response.redirected && ! isJson)) {
            stopped = true;
            throw new Error(data.message || 'Your session may have expired. Refresh the page and sign in again.');
        }
        if (! response.ok || ! isJson) throw new Error(data.message || 'Could not update notifications. Please try again.');
        return data;
    };

    const apply = (target, data) => {
        if (target !== root() || typeof data.html !== 'string' || ! Number.isInteger(data.unread_count)) return;
        const badge = target.querySelector('[data-notification-count]');
        const menu = target.querySelector('[data-notification-menu]');
        const focusedAction = menu.contains(document.activeElement)
            ? document.activeElement.closest('form')?.action : null;
        badge.hidden = data.unread_count === 0;
        badge.textContent = data.unread_count > 9 ? '9+' : String(data.unread_count);
        target.querySelector('[data-bs-toggle="dropdown"]').setAttribute('aria-label',
            data.unread_count ? `Notifications, ${data.unread_count} unread` : 'Notifications');
        menu.innerHTML = data.html;
        if (focusedAction) {
            Array.from(menu.querySelectorAll('form')).find((form) => form.action === focusedAction)
                ?.querySelector('button')?.focus({ preventScroll: true });
        }
    };

    async function refresh() {
        const target = root();
        if (document.hidden || stopped || submitting || window.portalAuthenticationRequired || ! target) return;
        if (polling && pollRoot === target) return;
        pause();
        const requestVersion = version;
        const controller = new AbortController();
        polling = controller;
        pollRoot = target;
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        try {
            const response = await (window.portalFetch || fetch)(target.dataset.notificationsUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store', signal: controller.signal,
            });
            if (requestVersion !== version) return;
            const data = await readResponse(response);
            if (requestVersion === version) apply(target, data);
        } catch (error) {
            // Keep the last displayed notifications during a temporary connection failure.
        } finally {
            window.clearTimeout(timeout);
            if (requestVersion === version) {
                polling = null;
                schedule();
            }
        }
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-notification-form]');
        if (! form) return;
        event.preventDefault();
        if (submitting) return;
        pause();
        submitting = true;
        const target = root();
        const startingUrl = window.location.href;
        const buttons = Array.from(form.querySelectorAll('button'), (button) => ({ button, disabled: button.disabled }));
        buttons.forEach(({ button }) => button.disabled = true);
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        try {
            const response = await (window.portalFetch || fetch)(form.action, {
                method: 'POST', body: new FormData(form),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store', signal: controller.signal,
            });
            const data = await readResponse(response);
            apply(target, data);
            try {
                localStorage.setItem('portal-notifications-updated', String(Date.now()));
            } catch (error) {
                // Other tabs will still receive the change on their next poll.
            }
            if (data.url && window.location.href === startingUrl) {
                const url = new URL(data.url, window.location.href);
                window.bootstrap?.Dropdown.getInstance(target.querySelector('[data-bs-toggle="dropdown"]'))?.hide();
                if (url.origin === window.location.origin && window.navigatePortalPage) {
                    await window.navigatePortalPage(url.href);
                } else {
                    window.location.assign(url.href);
                }
            } else if (data.message) {
                window.showPortalMessage?.(data.message);
            }
        } catch (error) {
            window.showPortalMessage?.(error.name === 'AbortError'
                ? 'The request timed out. Please try again.' : error.message, 'warning');
        } finally {
            window.clearTimeout(timeout);
            submitting = false;
            buttons.forEach(({ button, disabled }) => button.disabled = disabled);
            schedule();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) pause();
        else refresh();
    });
    document.addEventListener('show.bs.dropdown', (event) => {
        if (event.target.closest('[data-notifications-root]')) refresh();
    });
    window.addEventListener('storage', (event) => {
        if (event.key === 'portal-notifications-updated') {
            pause();
            refresh();
        }
    });
    window.addEventListener('pagehide', pause);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) refresh();
    });
    window.refreshPortalNotifications = refresh;
    schedule();
})();
