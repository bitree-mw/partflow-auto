function dismissDialog(dialog) {
    if (typeof dialog.close === 'function' && dialog.open) {
        dialog.close();
        return;
    }

    dialog.removeAttribute('open');
    dialog.remove();
}

export function initAppDialogs() {
    document.querySelectorAll('.app-alert-dialog').forEach((dialog) => {
        dialog.querySelectorAll('form[method="dialog"]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                dismissDialog(dialog);
            });
        });

        dialog.querySelectorAll('button').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                dismissDialog(dialog);
            });
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dismissDialog(dialog);
            }
        });

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            dismissDialog(dialog);
        });

        dialog.addEventListener('close', () => {
            dialog.remove();
        });
    });
}
