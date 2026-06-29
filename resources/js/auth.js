document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = button.closest('.password-control')?.querySelector('[data-password-input]');

    button.addEventListener('click', () => {
        if (! input) {
            return;
        }

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.textContent = isPassword ? 'Hide' : 'Show';
    });
});

document.querySelectorAll('[data-login-form]').forEach((form) => {
    const submitButton = form.querySelector('[data-login-submit]');
    const submitLabel = form.querySelector('[data-login-submit-label]');

    form.addEventListener('submit', () => {
        if (! submitButton) {
            return;
        }

        submitButton.disabled = true;
        submitButton.classList.add('is-loading');

        if (submitLabel) {
            submitLabel.textContent = 'Signing in...';
        }
    });
});
