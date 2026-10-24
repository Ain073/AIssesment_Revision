(() => {
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
})();
