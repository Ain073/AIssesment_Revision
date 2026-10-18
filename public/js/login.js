(() => {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const fields = document.getElementById('loginFields');
    const button = document.getElementById('loginButton');
    const label = document.getElementById('loginButtonLabel');
    const icon = document.getElementById('loginButtonIcon');
    const message = document.getElementById('loginLockoutMessage');
    const countdown = document.getElementById('loginCountdown');
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    const retryAfter = Math.max(0, Number(form.dataset.loginRetryAfter) || 0);
    // Use elapsed time, not interval ticks: background tabs can delay timers.
    const deadline = Date.now() + retryAfter * 1000;
    let submitting = false;
    let timer;

    const remainingSeconds = () => Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    const render = () => {
        const remaining = remainingSeconds();
        fields.disabled = remaining > 0;
        button.disabled = remaining > 0 || submitting;
        icon.classList.toggle('spinner-icon', submitting);

        if (remaining > 0) {
            message.hidden = false;
            countdown.textContent = String(remaining);
            label.textContent = `Try again in ${remaining}s`;
            icon.textContent = 'hourglass_top';
        } else {
            label.textContent = submitting ? 'Authenticating...' : 'Log In';
            icon.textContent = submitting ? 'progress_activity' : 'login';
            if (retryAfter > 0) {
                message.textContent = 'You can try logging in again.';
                message.classList.remove('alert-warning');
                message.classList.add('alert-info');
            }
            if (timer !== undefined) {
                window.clearInterval(timer);
                timer = undefined;
            }
        }
    };

    form.addEventListener('submit', (event) => {
        if (remainingSeconds() > 0 || submitting) {
            event.preventDefault();
            return;
        }
        submitting = true;
        // Only disable the submit button here; disabled inputs are omitted from POST data.
        render();
    });

    toggle.addEventListener('click', () => {
        if (fields.disabled || submitting) return;
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.querySelector('.material-symbols-outlined').textContent = show ? 'visibility_off' : 'visibility';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    window.addEventListener('pageshow', () => {
        submitting = false;
        render();
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) render();
    });

    render();
    if (remainingSeconds() > 0) timer = window.setInterval(render, 1000);
})();
