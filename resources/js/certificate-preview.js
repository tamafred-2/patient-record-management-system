function initializeCertificatePreview() {
    const form = document.querySelector('[data-certificate-form]');
    const frame = document.querySelector('[data-certificate-preview]');
    const container = document.querySelector('[data-certificate-preview-container]');
    if (!form || !frame || !container) return;

    function sync() {
        const document = frame.contentDocument;
        if (!document?.querySelector('.paper')) return;
        document.querySelectorAll('[data-certificate-field]').forEach(element => {
            const input = form.querySelector(`#${element.dataset.certificateField}`);
            if (input) element.textContent = input.value;
        });
        const findings = document.querySelector('[data-certificate-findings]');
        if (findings) findings.hidden = !form.querySelector('#findings').value.trim();
        const vitals = document.querySelector('[data-certificate-vitals]');
        if (vitals) vitals.hidden = form.querySelector('#template').value !== 'examination';
        const scale = Math.min(container.clientWidth / 1123, 1);
        const height = document.querySelector('.paper').offsetHeight;
        frame.style.height = `${height}px`;
        frame.style.transform = `scale(${scale})`;
        container.style.height = `${Math.ceil(height * scale)}px`;
    }

    frame.addEventListener('load', sync);
    // Run after Alpine updates the template and the reset-wording button.
    const schedule = () => requestAnimationFrame(sync);
    ['input', 'change', 'click'].forEach(event => form.addEventListener(event, schedule));
    const observer = new ResizeObserver(schedule);
    observer.observe(container);
    window.addEventListener('pagehide', () => observer.disconnect());
    window.addEventListener('pageshow', () => { observer.observe(container); schedule(); });
    sync();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCertificatePreview, { once: true });
} else {
    initializeCertificatePreview();
}
