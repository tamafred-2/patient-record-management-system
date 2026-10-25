// Historical filters only: appointment and clinical-entry dates have their own rules.
function initializeDateFilters() {
    const shift = (value, days) => {
        const date = new Date(`${value}T00:00:00Z`);
        date.setUTCDate(date.getUTCDate() + days);
        return date.toISOString().slice(0, 10);
    };
    document.querySelectorAll('form').forEach(form => {
        const fields = [...form.querySelectorAll('[data-history-date]')];
        if (!fields.length) return;
        const today = fields[0].max; // Use the server's Asia/Manila date.
        const from = fields.find(field => field.name === 'from');
        const to = fields.find(field => field.name === 'to');
        const rangeDays = fields[0].dataset.rangeDays ? Number(fields[0].dataset.rangeDays) : null;
        const errors = new Map(fields.map(field => {
            const error = document.createElement('p');
            error.id = `${field.id}-date-error`;
            error.className = 'mt-1 text-sm text-red-700';
            error.setAttribute('aria-live', 'polite');
            error.hidden = true;
            field.after(error);
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
            return [field, error];
        }));
        function update() {
            for (const field of fields) { field.max = today; field.removeAttribute('min'); }
            if (from && to) {
                if (to.value) {
                    from.max = to.value < today ? to.value : today;
                    if (rangeDays !== null) from.min = shift(to.value, -rangeDays);
                }
                if (from.value) {
                    to.min = from.value;
                    if (rangeDays !== null) to.max = [today, shift(from.value, rangeDays)].sort()[0];
                }
            }
            for (const field of fields) {
                let message = '';
                if (!field.disabled && field.value) {
                    if (field.value > today) message = 'Future dates cannot be used for this filter.';
                    else if (from?.value && to?.value && from.value > to.value) message = 'From date must be on or before To date.';
                    else if (field.validity.rangeUnderflow || field.validity.rangeOverflow) message = 'Choose a range of no more than 366 dates.';
                    else if (field.validity.badInput) message = 'Enter a valid date.';
                }
                field.setCustomValidity(message);
                field.setAttribute('aria-invalid', String(Boolean(message)));
                const error = errors.get(field);
                error.textContent = message;
                error.hidden = !message;
            }
        }
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        // Alpine toggles disabled calendars for All dates and report presets.
        const observer = new MutationObserver(update);
        fields.forEach(field => observer.observe(field, { attributes: true, attributeFilter: ['disabled'] }));
        update();
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeDateFilters, { once: true });
else initializeDateFilters();
