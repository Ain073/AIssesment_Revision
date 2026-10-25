(() => {
    const documentControl = document.querySelector('.report-document-control');
    if (!documentControl || !/(?:Chrome|Chromium|Edg)\//.test(navigator.userAgent)) return;

    const style = document.createElement('style');
    document.head.appendChild(style);

    const cssString = (value) => `"${value.replace(/[\r\n\f]/g, ' ').replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
    const updatePageCode = () => {
        const lines = [...documentControl.querySelectorAll('.report-document-control-line')]
            .map((line) => line.querySelector('input')?.value ?? line.textContent.trim());
        const content = lines.map(cssString).join(' "\\A" ');

        style.textContent = `@media print { @page { @top-right {
            content: ${content};
            color: #6b7280;
            font: italic 7pt Arial, Helvetica, sans-serif;
            text-align: right;
            vertical-align: top;
            white-space: pre;
            padding-top: 0.35in;
        } } }`;
    };

    documentControl.addEventListener('input', updatePageCode);
    window.addEventListener('beforeprint', updatePageCode);
    updatePageCode();
    document.documentElement.setAttribute('data-report-page-margins', '');
})();
