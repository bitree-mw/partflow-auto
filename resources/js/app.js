import './bootstrap';
import { initAppDialogs } from './modules/dialogs';
import { initSearchableSelects } from './modules/searchable-selects';

function initGlobalSiteSwitcher() {
    const form = document.querySelector('[data-global-site-form]');

    if (!form || document.body.classList.contains('pos-page')) {
        return;
    }

    const selector = form.querySelector('[data-global-site-selector]');
    const passwordInput = form.querySelector('[data-global-site-password]');
    const submitButton = form.querySelector('[data-global-site-submit]');
    const errorTarget = form.querySelector('[data-global-site-error]');
    const canChangeDirectly = form.dataset.canChangeDirectly === '1';
    let previousSiteId = selector?.value || '';

    const showError = (message = '') => {
        if (!errorTarget) {
            return;
        }

        errorTarget.textContent = message;
        errorTarget.hidden = message === '';
    };

    const setPasswordVisible = (visible) => {
        if (passwordInput) {
            passwordInput.hidden = !visible;
            passwordInput.required = visible;
        }

        if (submitButton) {
            submitButton.hidden = !visible;
        }
    };

    const submitSiteChange = async () => {
        showError('');

        const formData = new FormData(form);
        const token = form.querySelector('input[name="_token"]')?.value;

        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: formData,
        });
        const payload = await response.json();

        if (!response.ok) {
            const message = payload.errors?.admin_password?.[0] || payload.message || 'Could not change branch.';
            throw new Error(message);
        }

        previousSiteId = selector?.value || '';
        const headerSiteName = document.querySelector('[data-header-site-name]');

        if (headerSiteName && payload.site?.name) {
            headerSiteName.textContent = payload.site.name;
        }

        window.location.reload();
    };

    selector?.addEventListener('change', () => {
        showError('');

        if (canChangeDirectly) {
            submitSiteChange().catch((error) => {
                if (selector) {
                    selector.value = previousSiteId;
                }

                showError(error.message);
            });
            return;
        }

        setPasswordVisible(true);
        passwordInput?.focus();
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        submitSiteChange().catch((error) => {
            if (selector) {
                selector.value = previousSiteId;
            }

            setPasswordVisible(false);
            showError(error.message);
        });
    });
}

initAppDialogs();
initSearchableSelects();
initGlobalSiteSwitcher();
