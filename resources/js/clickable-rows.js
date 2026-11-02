// Delegate so cursor-loaded dashboard rows work without reinitialization.
document.addEventListener('click', event => {
    if (!(event.target instanceof Element) || event.button !== 0) return;
    const row = event.target.closest('[data-clickable-row]');
    if (!row || event.target.closest('a, button, input, select, textarea, label, [contenteditable]')) return;
    if (window.getSelection()?.toString()) return;
    const link = row.querySelector('a[data-row-link]');
    if (!link) return;
    link.dispatchEvent(new MouseEvent('click', {
        bubbles: true, cancelable: true, view: window,
        ctrlKey: event.ctrlKey, metaKey: event.metaKey,
        shiftKey: event.shiftKey, altKey: event.altKey,
    }));
});
