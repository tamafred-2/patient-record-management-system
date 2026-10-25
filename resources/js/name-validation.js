document.querySelectorAll('[data-no-numbers], [data-contact-number], [data-digits-only]').forEach((input) => {
    const error = document.getElementById(`${input.id}-number-error`);
    const serverError = document.getElementById(`${input.id}-error`);

    function validate(edited = false) {
        const invalid = input.hasAttribute('data-contact-number')
            ? input.value !== '' && !/^[0-9]{11}$/.test(input.value)
            : input.hasAttribute('data-digits-only')
                ? input.value !== '' && !/^[0-9]+$/.test(input.value)
                : /\p{Nd}/u.test(input.value);
        input.setCustomValidity(invalid ? error.textContent : '');
        error.hidden = !invalid;
        if (serverError && (edited || invalid)) serverError.hidden = true;

        const visibleError = invalid ? error : serverError && !serverError.hidden ? serverError : null;
        input.classList.toggle('border-red-500', !!visibleError);
        input.classList.toggle('border-slate-300', !visibleError);
        if (visibleError) {
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', visibleError.id);
        } else {
            input.removeAttribute('aria-invalid');
            input.removeAttribute('aria-describedby');
        }
    }

    input.addEventListener('input', () => validate(true));
    input.addEventListener('change', () => validate(true));
    validate();
});
