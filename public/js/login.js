(() => {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const fields = document.getElementById('loginFields');
    const button = document.getElementById('loginButton');
    const label = document.getElementById('loginButtonLabel');
    const icon = document.getElementById('loginButtonIcon');
    const message = document.getElementById('loginLockoutMessage');
    const countdown = document.getElementById('loginCountdown');
    const lockoutText = document.getElementById('loginLockoutText');
    const lockoutReady = document.getElementById('loginLockoutReady');
    const errorMessage = document.getElementById('loginErrorMessage');
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    const retryAfter = Math.max(0, Number(form.dataset.loginRetryAfter) || 0);
    const useAjax = typeof window.fetch === 'function' && typeof window.FormData === 'function';
    // Background tabs can delay timers, so calculate from the actual deadline.
    let deadline = Date.now() + retryAfter * 1000;
    let hadLockout = retryAfter > 0;
    let submitting = false;
    let requestPending = false;
    let timer;

    const remainingSeconds = () => Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    const render = () => {
        const remaining = remainingSeconds();
        // AJAX captures FormData before disabling inputs; native POST needs them enabled.
        fields.disabled = remaining > 0 || (useAjax && submitting);
        button.disabled = remaining > 0 || submitting;
        form.setAttribute('aria-busy', submitting ? 'true' : 'false');
        icon.classList.toggle('spinner-icon', submitting);
        message.hidden = !hadLockout;
        lockoutText.hidden = remaining === 0;
        lockoutReady.hidden = remaining > 0;
        message.classList.toggle('alert-warning', remaining > 0);
        message.classList.toggle('alert-info', remaining === 0);

        if (remaining > 0) {
            countdown.textContent = String(remaining);
            label.textContent = `Try again in ${remaining}s`;
            icon.textContent = 'hourglass_top';
        } else {
            label.textContent = submitting ? 'Authenticating...' : 'Log In';
            icon.textContent = submitting ? 'progress_activity' : 'login';
            if (timer !== undefined) {
                window.clearInterval(timer);
                timer = undefined;
            }
        }
    };

    const startLockout = (seconds) => {
        const duration = Number(seconds);
        const wait = seconds !== null && seconds !== '' && Number.isFinite(duration) ? Math.max(0, Math.ceil(duration)) : 60;
        deadline = Date.now() + wait * 1000;
        hadLockout = true;
        if (wait > 0 && timer === undefined) timer = window.setInterval(render, 1000);
        render();
    };

    const showError = (text) => {
        errorMessage.textContent = text;
        errorMessage.hidden = false;
    };

    const refreshCsrfToken = (token) => {
        if (typeof token !== 'string' || token === '') return;
        const input = form.querySelector('[name="_token"]');
        if (input) input.value = token;
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
        window.csrfToken = token;
    };

    const submitLogin = async (body) => {
        let navigating = false;
        const controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
        const timeout = controller ? window.setTimeout(() => controller.abort(), 20000) : undefined;
        try {
            const response = await window.fetch(form.action, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                ...(controller ? { signal: controller.signal } : {}),
            });
            if (response.status === 419) {
                showError('Your login session expired. Refresh this page and try again.');
                return;
            }
            if (!response.headers.get('Content-Type')?.includes('application/json')) {
                throw new Error('Unexpected login response.');
            }
            const data = await response.json();
            refreshCsrfToken(data.csrf_token);

            if (response.status === 429) {
                password.value = '';
                startLockout(data.retry_after ?? response.headers.get('Retry-After'));
            } else if (response.status === 422) {
                const firstError = Object.values(data.errors || {}).flat().find(value => typeof value === 'string');
                showError(firstError || 'Unable to log in. Please check your details.');
                password.value = '';
            } else if (response.ok && typeof data.redirect === 'string') {
                const target = new URL(data.redirect, window.location.href);
                if (target.origin !== window.location.origin || !['http:', 'https:'].includes(target.protocol)) {
                    throw new Error('Invalid login redirect.');
                }
                window.location.assign(target.href);
                navigating = true;
            } else {
                throw new Error('Login could not be completed.');
            }
        } catch (error) {
            showError(error.name === 'AbortError'
                ? 'Login took too long. Check your connection and try again.'
                : 'Unable to complete login. Check your connection or refresh this page and try again.');
        } finally {
            if (timeout !== undefined) window.clearTimeout(timeout);
            requestPending = false;
            if (!navigating) submitting = false;
            render();
            if (!navigating && !fields.disabled && !errorMessage.hidden) password.focus();
        }
    };

    form.addEventListener('submit', (event) => {
        if (remainingSeconds() > 0 || submitting) {
            event.preventDefault();
            return;
        }
        if (!useAjax) {
            submitting = true;
            render();
            return;
        }
        event.preventDefault();
        // Serialize credentials before rendering the disabled pending state.
        const body = new window.FormData(form);
        submitting = true;
        requestPending = true;
        hadLockout = false;
        errorMessage.hidden = true;
        render();
        void submitLogin(body);
    });

    toggle.addEventListener('click', () => {
        if (fields.disabled || submitting) return;
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.querySelector('.material-symbols-outlined').textContent = show ? 'visibility_off' : 'visibility';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    window.addEventListener('pageshow', () => {
        if (!requestPending) submitting = false;
        render();
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) render();
    });

    render();
    if (remainingSeconds() > 0) timer = window.setInterval(render, 1000);
})();
