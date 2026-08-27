function dismissDialog(dialog) {
    if (dialog.classList.contains('is-closing')) {
        return;
    }

    dialog.classList.add('is-closing');
    let finished = false;

    const finish = () => {
        if (finished) {
            return;
        }

        finished = true;

        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
            return;
        }

        dialog.removeAttribute('open');
        dialog.remove();
    };

    dialog.addEventListener('animationend', finish, { once: true });
    window.setTimeout(finish, 220);
}

let confirmationListenerBound = false;

function isEditForm(form) {
    const method = form.querySelector('input[name="_method"]')?.value?.toUpperCase();

    return method === 'PUT' || method === 'PATCH';
}

function requestConfirmation({
    title = 'Confirm action',
    message,
    confirmLabel = 'Confirm',
    confirmButtonClass = 'btn-danger',
}) {
    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        const card = document.createElement('div');
        const header = document.createElement('header');
        const eyebrow = document.createElement('span');
        const heading = document.createElement('h2');
        const copy = document.createElement('p');
        const actions = document.createElement('div');
        const cancelButton = document.createElement('button');
        const confirmButton = document.createElement('button');
        let settled = false;

        dialog.className = 'app-confirm-dialog';
        card.className = 'app-confirm-dialog-card';
        eyebrow.className = 'eyebrow';
        eyebrow.textContent = 'Please confirm';
        heading.textContent = title;
        copy.textContent = message;
        actions.className = 'app-confirm-dialog-actions';
        cancelButton.className = 'btn-secondary';
        cancelButton.type = 'button';
        cancelButton.textContent = 'Cancel';
        confirmButton.className = confirmButtonClass;
        confirmButton.type = 'button';
        confirmButton.textContent = confirmLabel;

        const finish = (confirmed) => {
            if (settled) {
                return;
            }

            settled = true;
            resolve(confirmed);
            dismissDialog(dialog);
        };

        cancelButton.addEventListener('click', () => finish(false));
        confirmButton.addEventListener('click', () => finish(true));
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                finish(false);
            }
        });
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(false);
        });

        header.append(eyebrow, heading);
        actions.append(cancelButton, confirmButton);
        card.append(header, copy, actions);
        dialog.append(card);
        document.body.append(dialog);
        dialog.addEventListener('close', () => dialog.remove(), { once: true });
        dialog.showModal();
        cancelButton.focus();
    });
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

    if (confirmationListenerBound) {
        return;
    }

    confirmationListenerBound = true;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        const submitter = event.submitter;
        const editForm = isEditForm(form);
        const message = submitter?.dataset.confirm
            || form.dataset.confirm
            || (editForm ? 'Save the changes you made?' : '');
        const confirmLabel = submitter?.dataset.confirmLabel
            || form.dataset.confirmLabel
            || (editForm ? 'Save changes' : 'Confirm');

        if (!message || form.dataset.confirmBypass === 'true') {
            return;
        }

        event.preventDefault();

        requestConfirmation({
            title: submitter?.dataset.confirmTitle
                || form.dataset.confirmTitle
                || (editForm ? 'Save changes?' : 'Confirm action'),
            message,
            confirmLabel,
            confirmButtonClass: editForm || confirmLabel === 'Save changes' ? 'btn' : 'btn-danger',
        }).then((confirmed) => {
            if (!confirmed) {
                submitter?.focus();
                return;
            }

            form.dataset.confirmBypass = 'true';

            try {
                if (submitter) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }
            } finally {
                delete form.dataset.confirmBypass;
            }
        });
    }, true);
}
