(() => {
    // Delegation keeps the confirmation working after roster polling and AJAX navigation.
    document.addEventListener('show.bs.modal', (event) => {
        const modal = event.target;
        if (!modal.matches('[data-remove-class-student-modal]')) return;

        const trigger = event.relatedTarget;
        const form = modal.querySelector('form');
        if (!trigger?.matches('[data-remove-class-student]') || !trigger.dataset.removeStudentUrl
            || !form || form.dataset.ajaxSubmitting === 'true') {
            event.preventDefault();
            return;
        }

        form.action = trigger.dataset.removeStudentUrl;
        modal.querySelector('[data-removal-student-name]').textContent = trigger.dataset.studentName;
        modal.querySelector('[data-removal-student-number]').textContent = trigger.dataset.studentNumber;
        modal.querySelector('[data-confirm-student-removal]').disabled = false;

        const errors = form.querySelector('[data-ajax-errors]');
        errors.textContent = '';
        errors.classList.add('d-none');
    });
})();
