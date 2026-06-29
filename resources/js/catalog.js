import { initTableSearch } from './modules/table-search';

initTableSearch();

document.querySelectorAll('[data-compatibility-list]').forEach((list) => {
    const addButton = document.querySelector('[data-add-compatibility-variant]');

    function bindRemove(button) {
        button.addEventListener('click', () => {
            const rows = list.querySelectorAll('.compatibility-variant-row');

            if (rows.length === 1) {
                rows[0].querySelector('input')?.focus();
                return;
            }

            button.closest('.compatibility-variant-row')?.remove();
        });
    }

    list.querySelectorAll('[data-remove-compatibility-variant]').forEach(bindRemove);

    addButton?.addEventListener('click', () => {
        const row = list.querySelector('.compatibility-variant-row')?.cloneNode(true);

        if (!row) {
            return;
        }

        const input = row.querySelector('input, select');
        const removeButton = row.querySelector('[data-remove-compatibility-variant]');

        if (input) {
            input.value = '';
        }

        if (removeButton) {
            bindRemove(removeButton);
        }

        list.appendChild(row);
        input?.focus();
    });
});

document.querySelectorAll('[data-car-make-select]').forEach((makeSelect) => {
    const form = makeSelect.closest('form');
    const modelSelect = form?.querySelector('[data-vehicle-model-select]');

    if (!modelSelect) {
        return;
    }

    const options = Array.from(modelSelect.options);

    function syncModels() {
        const selectedMakeId = makeSelect.value;
        const currentValue = modelSelect.value;
        let currentValueStillVisible = false;

        options.forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                option.textContent = selectedMakeId ? 'Select model' : 'Select make first';
                return;
            }

            const visible = option.dataset.carMakeId === selectedMakeId;
            option.hidden = !visible;

            if (visible && option.value === currentValue) {
                currentValueStillVisible = true;
            }
        });

        if (!currentValueStillVisible) {
            modelSelect.value = '';
        }

        modelSelect.disabled = !selectedMakeId;
        modelSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    makeSelect.addEventListener('change', syncModels);
    syncModels();
});
