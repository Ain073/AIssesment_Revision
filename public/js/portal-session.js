(() => {
    const showSessionNotice = (message, passwordSetup = false) => {
        window.portalAuthenticationRequired = true;
        let notice = document.querySelector('[data-portal-session-notice]');
        if (! notice) {
            notice = document.createElement('div');
            notice.dataset.portalSessionNotice = 'true';
            notice.className = 'alert alert-warning shadow';
            notice.setAttribute('role', 'alert');
            notice.style.cssText = 'position:fixed;top:5.5rem;right:1.25rem;z-index:2000;max-width:420px';
            document.body.appendChild(notice);
        }

        const text = document.createElement('p');
        text.className = 'mb-2';
        text.textContent = message;
        const link = document.createElement('a');
        link.className = 'btn btn-sm btn-warning';
        link.href = passwordSetup ? document.body.dataset.passwordSetupUrl : document.body.dataset.portalLoginUrl;
        link.textContent = passwordSetup ? 'Set your password' : 'Sign in again';
        notice.replaceChildren(text, link);
    };

    // Upload requests use XHR for progress events and share the same session notices.
    window.handlePortalSessionResponse = (status, data = {}) => {
        data = data && typeof data === 'object' ? data : {};
        if (status === 401 || data.code === 'account_inactive') {
            showSessionNotice(data.message || 'Your session has ended. Keep a copy of unsaved work and sign in again.');
        } else if (status === 419) {
            showSessionNotice('Your session could not be verified. Keep a copy of unsaved work and sign in again.');
        } else if (data.code === 'password_change_required') {
            showSessionNotice(data.message, true);
        }
    };

    window.portalFetch = async (input, options) => {
        const response = await fetch(input, options);
        if (! [401, 403, 419].includes(response.status)) return response;

        // Read a copy so each caller can still display its own validation/error response.
        let data = {};
        try {
            data = await response.clone().json();
        } catch (error) {
            // An expired CSRF token may produce an HTML error page.
        }
        window.handlePortalSessionResponse(response.status, data);

        return response;
    };
})();
