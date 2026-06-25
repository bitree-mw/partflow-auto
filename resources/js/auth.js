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
