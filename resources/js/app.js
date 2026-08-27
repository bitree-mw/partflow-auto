import './bootstrap';
import { initAppDialogs } from './modules/dialogs';
import { initSearchableSelects } from './modules/searchable-selects';

document.documentElement.classList.add('js');

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

function initMobileSidebar() {
    const frame = document.querySelector('[data-app-frame]');
    const main = document.querySelector('.app-main');
    const sidebar = document.querySelector('[data-app-sidebar]');
    const toggle = sidebar?.querySelector('[data-sidebar-toggle]');
    const collapseToggle = sidebar?.querySelector('[data-sidebar-collapse]');
    const navigation = sidebar?.querySelector('[data-sidebar-navigation]');
    const mobileToggle = document.querySelector('[data-mobile-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const mobileQuery = window.matchMedia('(max-width: 1120px)');
    let mobileTrigger = null;

    if (!sidebar || !toggle || !navigation) {
        return;
    }

    const setCollapsed = (collapsed) => {
        const desktopCollapsed = !mobileQuery.matches && collapsed;

        frame?.classList.toggle('sidebar-collapsed', desktopCollapsed);
        collapseToggle?.setAttribute('aria-pressed', desktopCollapsed ? 'true' : 'false');
        collapseToggle?.setAttribute('aria-label', desktopCollapsed ? 'Expand navigation' : 'Collapse navigation');

        if (collapseToggle) {
            collapseToggle.querySelector('span').textContent = desktopCollapsed ? '›' : '‹';
        }
    };

    const setOpen = (open, restoreFocus = true) => {
        const mobileOpen = mobileQuery.matches && open;

        sidebar.classList.toggle('sidebar-open', mobileOpen);
        document.body.classList.toggle('mobile-nav-open', mobileOpen);
        if (main) {
            main.inert = mobileOpen;
        }
        toggle.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false');
        mobileToggle?.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false');
        mobileToggle?.setAttribute('aria-label', mobileOpen ? 'Close navigation' : 'Open navigation');
        toggle.querySelector('strong').textContent = mobileOpen ? 'Close menu' : 'Menu';

        if (mobileOpen) {
            mobileTrigger = document.activeElement;
            window.requestAnimationFrame(() => navigation.querySelector('a')?.focus());
        } else if (restoreFocus && mobileTrigger instanceof HTMLElement) {
            mobileTrigger.focus();
            mobileTrigger = null;
        }
    };

    toggle.addEventListener('click', () => {
        setOpen(!sidebar.classList.contains('sidebar-open'));
    });

    mobileToggle?.addEventListener('click', () => {
        setOpen(!sidebar.classList.contains('sidebar-open'));
    });

    backdrop?.addEventListener('click', () => setOpen(false));

    collapseToggle?.addEventListener('click', () => {
        const collapsed = !frame?.classList.contains('sidebar-collapsed');

        window.localStorage.setItem('partflow-sidebar-collapsed', collapsed ? '1' : '0');
        setCollapsed(collapsed);
    });

    navigation.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            setOpen(false, false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('sidebar-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    mobileQuery.addEventListener('change', () => {
        setOpen(false);
        setCollapsed(window.localStorage.getItem('partflow-sidebar-collapsed') === '1');
    });
    setOpen(false);
    setCollapsed(window.localStorage.getItem('partflow-sidebar-collapsed') === '1');
}

function initMobileFilters() {
    document.querySelectorAll('.filter-form').forEach((form, index) => {
        const id = form.id || `mobile-filter-panel-${index + 1}`;
        const toggle = document.createElement('button');

        form.id = id;
        toggle.type = 'button';
        toggle.className = 'mobile-filter-toggle';
        toggle.setAttribute('aria-controls', id);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<span aria-hidden="true"></span><strong>Filter options</strong>';

        toggle.addEventListener('click', () => {
            const open = !form.classList.contains('mobile-filter-open');

            form.classList.toggle('mobile-filter-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.querySelector('strong').textContent = open ? 'Hide filters' : 'Filter options';
        });

        form.before(toggle);
    });
}

function initResponsiveTables() {
    document.querySelectorAll('.table-wrap').forEach((wrapper) => {
        if (!wrapper.hasAttribute('tabindex')) {
            wrapper.tabIndex = 0;
        }

        wrapper.setAttribute('role', 'region');

        if (!wrapper.hasAttribute('aria-label')) {
            const panel = wrapper.closest('.data-panel');
            const heading = panel?.querySelector('h2, h3');

            wrapper.setAttribute('aria-label', heading?.textContent?.trim() || 'Scrollable data table');
        }
    });
}

function initUnsavedChanges() {
    const trackedForms = Array.from(document.querySelectorAll('form[data-track-unsaved-changes]'))
        .filter((form) => !form.matches('[data-ignore-unsaved-changes]'));

    if (trackedForms.length === 0) {
        return;
    }

    const dirtyForms = new Set();
    const initialStates = new WeakMap();

    const formState = (form) => JSON.stringify(Array.from(new FormData(form).entries()).map(([key, value]) => [
        key,
        value instanceof File ? [value.name, value.size, value.lastModified] : value,
    ]));

    trackedForms.forEach((form) => initialStates.set(form, formState(form)));

    const refreshDirtyState = (form) => {
        if (formState(form) === initialStates.get(form)) {
            dirtyForms.delete(form);
            return;
        }

        dirtyForms.add(form);
    };

    trackedForms.forEach((form) => {
        form.addEventListener('input', () => refreshDirtyState(form));
        form.addEventListener('change', () => refreshDirtyState(form));
    });

    document.addEventListener('submit', (event) => {
        if (!event.defaultPrevented && trackedForms.includes(event.target)) {
            dirtyForms.delete(event.target);
        }
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || dirtyForms.size === 0 || link.hasAttribute('download') || link.target === '_blank') {
            return;
        }

        const destination = new URL(link.href, window.location.href);
        const current = new URL(window.location.href);
        const staysOnPage = destination.origin === current.origin
            && destination.pathname === current.pathname
            && destination.search === current.search;

        if (staysOnPage || window.confirm('Are you sure you want to leave without saving?')) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);

    window.addEventListener('beforeunload', (event) => {
        if (dirtyForms.size === 0) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
}

function initNoFutureDates() {
    const inputs = Array.from(document.querySelectorAll('input[type="datetime-local"][data-no-future-date]'));

    if (inputs.length === 0) {
        return;
    }

    const refreshMaximum = () => {
        const currentTime = new Date();
        const localTime = new Date(currentTime.getTime() - (currentTime.getTimezoneOffset() * 60_000));
        const maximum = localTime.toISOString().slice(0, 16);

        inputs.forEach((input) => {
            input.max = maximum;
        });
    };

    refreshMaximum();
    inputs.forEach((input) => input.addEventListener('focus', refreshMaximum));
    window.setInterval(refreshMaximum, 30_000);
}

initAppDialogs();
initSearchableSelects();
initGlobalSiteSwitcher();
initMobileSidebar();
initMobileFilters();
initResponsiveTables();
initUnsavedChanges();
initNoFutureDates();
