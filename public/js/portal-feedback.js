(() => {
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


    window.showPortalMessage = showPortalMessage;
    window.portalFeedback = { movePageFlashAlertsToToast, autoDismissPageInfoAlerts, cleanupBootstrapOverlays, cleanupStaleBootstrapBackdrops };
})();
