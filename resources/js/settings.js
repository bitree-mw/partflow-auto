// Settings wizard navigation and dialog behavior.
const settingsContent = document.querySelector('[data-settings-content]');
const tabButtons = Array.from(document.querySelectorAll('[data-settings-tab]'));
const tabPanels = Array.from(document.querySelectorAll('[data-settings-panel]'));
const progressLabel = document.querySelector('[data-settings-progress]');
const previousButton = document.querySelector('[data-settings-prev]');
const nextButton = document.querySelector('[data-settings-next]');
const activePanelInput = document.querySelector('[data-settings-active-panel]');
const dialogButtons = Array.from(document.querySelectorAll('[data-open-settings-dialog]'));
const dialogs = Array.from(document.querySelectorAll('[data-settings-dialog]'));
const errorDialogMap = {
    create_user: 'user',
    update_user: 'edit-user',
    create_car_make: 'car-make',
    create_vehicle_model: 'vehicle-model',
    update_car_make: 'edit-car-make',
    update_vehicle_model: 'edit-vehicle-model',
};
const listSearches = Array.from(document.querySelectorAll('[data-settings-list-search]'));
const logoInput = document.querySelector('[data-logo-input]');
const logoPreview = document.querySelector('[data-logo-preview]');
const logoFileName = document.querySelector('[data-logo-file-name]');
const themeColorInputs = Array.from(document.querySelectorAll('[data-theme-color]'));

let activeIndex = 0;

function activateTab(index) {
    activeIndex = Math.max(0, Math.min(index, tabButtons.length - 1));
    const activeKey = tabButtons[activeIndex]?.dataset.settingsTab;

    tabButtons.forEach((button) => {
        const isActive = button.dataset.settingsTab === activeKey;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-selected', String(isActive));
        button.tabIndex = isActive ? 0 : -1;
    });

    tabPanels.forEach((panel) => {
        const isActive = panel.dataset.settingsPanel === activeKey;
        panel.hidden = !isActive;
        panel.classList.toggle('active', isActive);
    });

    if (progressLabel) {
        progressLabel.textContent = `Step ${activeIndex + 1} of ${tabButtons.length}`;
    }

    if (previousButton) {
        previousButton.disabled = activeIndex === 0;
    }

    if (nextButton) {
        nextButton.disabled = activeIndex === tabButtons.length - 1;
    }

    if (activePanelInput && activeKey) {
        activePanelInput.value = activeKey;
    }

    if (activeKey) {
        window.history.replaceState(null, '', `#${activeKey}`);
    }
}

function openDialog(name) {
    const dialog = dialogs.find((item) => item.dataset.settingsDialog === name);

    if (!dialog) {
        return;
    }

    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
    }

    dialog.querySelector('input, select, textarea')?.focus();
}

function closeDialog(dialog) {
    if (typeof dialog.close === 'function') {
        dialog.close();
    } else {
        dialog.removeAttribute('open');
    }
}

tabButtons.forEach((button, index) => {
    button.addEventListener('click', () => activateTab(index));
    button.addEventListener('keydown', (event) => {
        const targetIndex = event.key === 'ArrowRight'
            ? (index + 1) % tabButtons.length
            : event.key === 'ArrowLeft'
                ? (index - 1 + tabButtons.length) % tabButtons.length
                : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                        ? tabButtons.length - 1
                        : null;

        if (targetIndex === null) {
            return;
        }

        event.preventDefault();
        activateTab(targetIndex);
        tabButtons[targetIndex]?.focus();
    });
});

previousButton?.addEventListener('click', () => activateTab(activeIndex - 1));
nextButton?.addEventListener('click', () => activateTab(activeIndex + 1));

dialogButtons.forEach((button) => {
    button.addEventListener('click', () => {
        if (button.hasAttribute('data-settings-fill')) {
            Object.entries(button.dataset).forEach(([key, value]) => {
                const field = document.querySelector(`[data-settings-field="${key}"]`);

                if (field) {
                    if (field instanceof HTMLInputElement && field.type === 'checkbox') {
                        field.checked = value === '1' || value === 'true';
                    } else {
                        field.value = value;
                    }
                }
            });

            if (button.dataset.vehicleModelAction) {
                document.querySelector('[data-dynamic-action]')?.setAttribute('action', button.dataset.vehicleModelAction);
            }
        }

        openDialog(button.dataset.openSettingsDialog);
    });
});

dialogs.forEach((dialog) => {
    dialog.querySelectorAll('[data-close-settings-dialog]').forEach((button) => {
        button.addEventListener('click', () => closeDialog(dialog));
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDialog(dialog);
        }
    });

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeDialog(dialog);
    });
});

const initialPanel = window.location.hash.replace('#', '') || settingsContent?.dataset.settingsInitialPanel;
const initialIndex = tabButtons.findIndex((button) => button.dataset.settingsTab === initialPanel);
activateTab(initialIndex >= 0 ? initialIndex : 0);

const errorDialog = errorDialogMap[settingsContent?.dataset.settingsErrorDialog];

if (errorDialog) {
    openDialog(errorDialog);
}

listSearches.forEach((input) => {
    const list = document.querySelector(`[data-settings-list="${input.dataset.settingsListSearch}"]`);
    const items = Array.from(list?.querySelectorAll('[data-settings-list-item]') ?? []);

    input.addEventListener('input', () => {
        const needle = input.value.trim().toLowerCase();

        items.forEach((item) => {
            item.hidden = needle !== '' && !item.dataset.settingsListItem.includes(needle);
        });
    });
});

logoInput?.addEventListener('change', () => {
    const [file] = logoInput.files ?? [];

    if (logoFileName) {
        logoFileName.textContent = file?.name || 'No file selected';
    }

    if (!file || !logoPreview) {
        return;
    }

    const image = document.createElement('img');
    image.className = 'company-logo-image';
    image.alt = 'New company logo preview';
    image.src = URL.createObjectURL(file);
    image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
    logoPreview.replaceChildren(image);
});

themeColorInputs.forEach((input) => {
    const output = document.querySelector(`[data-theme-color-output="${input.dataset.themeColor}"]`);

    input.addEventListener('input', () => {
        if (output) {
            output.textContent = input.value.toUpperCase();
        }
    });
});
