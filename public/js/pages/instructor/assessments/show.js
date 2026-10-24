(() => {
    const stepButtons = document.querySelectorAll('[data-step-tab]');
    const stepPanels = document.querySelectorAll('[data-step-panel]');

    const showPanel = (target) => {
        stepPanels.forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.stepPanel !== target);
        });

        stepButtons.forEach((button) => {
            button.setAttribute('aria-selected', button.dataset.stepTarget === target ? 'true' : 'false');
        });
    };

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => showPanel(button.dataset.stepTarget));
    });
})();
