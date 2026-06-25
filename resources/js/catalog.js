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
