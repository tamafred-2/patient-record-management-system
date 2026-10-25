function initializeValidationModal() {
    const dialog = document.getElementById('validation-modal');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const list = dialog.querySelector('[data-validation-messages]');
    let target = null;
    let pending = false;

    function open() {
        if (!dialog.open) dialog.showModal();
    }

    dialog.querySelector('[data-validation-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        if (target && !target.disabled && target.getClientRects().length) {
            target.focus();
            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    });

    const serverMessages = [...list.querySelectorAll('[data-error-field]')];
    if (serverMessages.length) {
        const fields = [...document.querySelectorAll('input, select, textarea')];
        target = fields.find(field => serverMessages.some(message => message.dataset.errorField === field.name)) ?? null;
        // Let page-specific editors initialize before opening the summary above them.
        if (document.readyState === 'complete') open();
        else window.addEventListener('load', open, { once: true });
    }

    // Aggregate native required/type/range and custom validity errors on submission.
    document.addEventListener('invalid', event => {
        const form = event.target.form;
        if (!form) return;
        event.preventDefault();
        if (pending) return;
        pending = true;
        setTimeout(() => {
            pending = false;
            const invalid = [...form.elements].filter(field => field.willValidate && !field.validity.valid);
            if (!invalid.length) return;
            target = invalid[0];
            list.replaceChildren();
            invalid.forEach(field => {
                const item = document.createElement('li');
                const label = field.labels?.[0]?.textContent.trim() || field.name || 'Field';
                item.textContent = `${label}: ${field.validationMessage}`;
                list.append(item);
            });
            open();
        }, 0);
    }, true);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeValidationModal, { once: true });
else initializeValidationModal();
