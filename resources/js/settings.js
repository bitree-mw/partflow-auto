// Local settings wizard behavior before API-backed persistence is connected.
const tabButtons = Array.from(document.querySelectorAll('[data-settings-tab]'));
const tabPanels = Array.from(document.querySelectorAll('[data-settings-panel]'));
const progressLabel = document.querySelector('[data-settings-progress]');
const previousButton = document.querySelector('[data-settings-prev]');
const nextButton = document.querySelector('[data-settings-next]');
const addSiteButton = document.querySelector('[data-add-site]');
const siteForm = document.querySelector('[data-site-form]');

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
}

tabButtons.forEach((button, index) => {
    button.addEventListener('click', () => activateTab(index));
});

previousButton?.addEventListener('click', () => activateTab(activeIndex - 1));
nextButton?.addEventListener('click', () => activateTab(activeIndex + 1));

addSiteButton?.addEventListener('click', () => {
    if (!siteForm) {
        return;
    }

    siteForm.hidden = false;
    addSiteButton.hidden = true;
    siteForm.querySelector('input')?.focus();
});

activateTab(0);
