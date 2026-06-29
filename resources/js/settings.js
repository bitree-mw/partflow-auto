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
    create_site: 'site',
    create_document_series: 'series',
    create_user: 'user',
};

let activeIndex = 0;

function activateTab(index) {
    activeIndex = Math.max(0, Math.min(index, tabButtons.length - 1));
    const activeKey = tabButtons[activeIndex]?.dataset.settingsTab;

    tabButtons.forEach((button) => {
        button.classList.toggle('active', button.dataset.settingsTab === activeKey);
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
});

previousButton?.addEventListener('click', () => activateTab(activeIndex - 1));
nextButton?.addEventListener('click', () => activateTab(activeIndex + 1));

dialogButtons.forEach((button) => {
    button.addEventListener('click', () => openDialog(button.dataset.openSettingsDialog));
});

dialogs.forEach((dialog) => {
    dialog.querySelectorAll('[data-close-settings-dialog]').forEach((button) => {
        button.addEventListener('click', () => closeDialog(dialog));
    });
});

const initialPanel = window.location.hash.replace('#', '') || settingsContent?.dataset.settingsInitialPanel;
const initialIndex = tabButtons.findIndex((button) => button.dataset.settingsTab === initialPanel);
activateTab(initialIndex >= 0 ? initialIndex : 0);

const errorDialog = errorDialogMap[settingsContent?.dataset.settingsErrorDialog];

if (errorDialog) {
    openDialog(errorDialog);
}
