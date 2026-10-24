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
        copyButton.querySelector('.copy-label').textContent = 'Copied';
    });
});
