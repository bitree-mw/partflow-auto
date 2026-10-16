// Submits a parts-import form in the background and shows a 0-100% progress overlay.
// The upload share of the bar follows real bytes sent; the server's review/import time cannot be
// measured, so the rest eases towards 95% and jumps to 100% when the server answers.

const CREEP_CEILING = 95;

function failureMessages(status, body) {
    if (status === 419) {
        return ['Your session has expired. Reload the page and try again.'];
    }

    if (status === 413) {
        return ['The file is too large for the server to accept.'];
    }

    const fieldErrors = Object.values(body?.errors ?? {}).flat().filter(Boolean);

    if (fieldErrors.length) {
        return body?.message && body.redirect ? [body.message, ...fieldErrors] : fieldErrors;
    }

    if (body?.message && status < 500) {
        return [body.message];
    }

    return ['The server could not complete the response. Check the purchases list before retrying to avoid posting the same stock twice. If no purchase was recorded, upload the sheet again.'];
}

function createOverlay(root) {
    const fill = root.querySelector('[data-import-progress-fill]');
    const bar = root.querySelector('[data-import-progress-bar]');
    const percentLabel = root.querySelector('[data-import-progress-percent]');
    const stageLabel = root.querySelector('[data-import-progress-stage]');
    const title = root.querySelector('[data-import-progress-title]');
    const steps = [...root.querySelectorAll('[data-import-progress-step]')];
    const errorBox = root.querySelector('[data-import-progress-error]');
    const errorList = root.querySelector('[data-import-progress-error-messages]');
    const closeButton = root.querySelector('[data-import-progress-close]');
    const note = root.querySelector('[data-import-progress-note]');
    const initialTitle = title.textContent;

    return {
        reset() {
            root.hidden = false;
            root.dataset.state = 'running';
            title.textContent = initialTitle;
            errorBox.hidden = true;
            note.hidden = false;
            this.render(0, 0);
        },
        render(percent, stepIndex) {
            const shown = Math.floor(percent);

            fill.style.width = `${percent}%`;
            percentLabel.textContent = `${shown}%`;
            bar.setAttribute('aria-valuenow', String(shown));
            stageLabel.textContent = steps[stepIndex]?.textContent.trim() ?? '';
            steps.forEach((step, index) => {
                step.dataset.state = index < stepIndex ? 'done' : index === stepIndex ? 'active' : '';
            });
        },
        complete(doneTitle) {
            root.dataset.state = 'done';
            title.textContent = doneTitle;
            fill.style.width = '100%';
            percentLabel.textContent = '100%';
            bar.setAttribute('aria-valuenow', '100');
            stageLabel.textContent = 'Done';
            steps.forEach((step) => { step.dataset.state = 'done'; });
        },
        fail(heading, messages, onClose) {
            root.dataset.state = 'error';
            title.textContent = heading;
            stageLabel.textContent = 'Request failed; completion could not be confirmed';
            note.hidden = true;
            errorList.replaceChildren(...messages.map((message) => {
                const item = document.createElement('li');
                item.textContent = message;
                return item;
            }));
            errorBox.hidden = false;
            closeButton.onclick = onClose;
            closeButton.focus();
        },
        hide() {
            root.hidden = true;
        },
    };
}

/**
 * @param {HTMLFormElement} form
 * @param {HTMLElement} root  the [data-import-progress] overlay
 * @param {{ uploadShare: number, stepStarts: number[], doneTitle: string, failTitle: string, onReset?: () => void }} options
 */
export function submitWithProgress(form, root, { uploadShare, stepStarts, doneTitle, failTitle, onReset }) {
    const overlay = createOverlay(root);
    const button = form.querySelector('button[type="submit"], button:not([type])');
    const xhr = new XMLHttpRequest();
    let percent = 0;
    let creepTimer = null;

    const stepFor = (value) => stepStarts.reduce((current, start, index) => (value >= start ? index : current), 0);
    const show = (value) => {
        percent = Math.max(percent, Math.min(value, CREEP_CEILING));
        overlay.render(percent, stepFor(percent));
    };
    const startCreep = () => {
        if (creepTimer) return;
        creepTimer = window.setInterval(() => {
            // Slows down as it nears the ceiling so long reviews still look alive.
            show(percent + Math.max((CREEP_CEILING - percent) * 0.04, 0.15));
        }, 150);
    };
    const stopCreep = () => window.clearInterval(creepTimer);
    const restore = () => {
        stopCreep();
        overlay.hide();
        if (button) button.disabled = false;
        onReset?.();
    };

    overlay.reset();
    if (button) button.disabled = true;

    xhr.open(form.method.toUpperCase(), form.action);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.responseType = 'json';

    xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable && event.total > 0) {
            show((event.loaded / event.total) * uploadShare);
        }
    });
    xhr.upload.addEventListener('load', () => {
        show(uploadShare);
        startCreep();
    });

    xhr.addEventListener('load', () => {
        stopCreep();
        const body = xhr.response;

        if (xhr.status >= 200 && xhr.status < 300 && body?.redirect) {
            overlay.complete(doneTitle);
            window.setTimeout(() => window.location.assign(body.redirect), 600);
            return;
        }

        overlay.fail(failTitle, failureMessages(xhr.status, body), () => {
            if (body?.redirect) {
                window.location.assign(body.redirect);
                return;
            }
            restore();
        });
    });

    xhr.addEventListener('error', () => {
        stopCreep();
        overlay.fail(failTitle, ['The connection was lost. Check your internet connection and the purchases list before retrying; the server may have completed the import.'], restore);
    });

    xhr.send(new FormData(form));
    // Bodies too small to report upload progress still move the bar.
    window.setTimeout(startCreep, 400);
}

export function initImportConfirmProgress() {
    const form = document.querySelector('[data-import-confirm-form]');
    const root = document.querySelector('[data-import-progress]');

    if (!form || !root) {
        return;
    }

    form.addEventListener('submit', (event) => {
        // The confirm dialog cancels the first submit and re-submits once the user agrees.
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        submitWithProgress(form, root, {
            uploadShare: 5,
            stepStarts: [0, 30, 60, 80],
            doneTitle: 'Inventory posted',
            failTitle: 'Could not confirm inventory posting',
        });
    });
}
