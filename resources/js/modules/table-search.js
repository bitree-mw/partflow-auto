export function initTableSearch(root = document) {
    root.querySelectorAll('[data-table-search]').forEach((input) => {
        const panel = input.closest('.data-panel');
        const table = panel?.querySelector('.data-table');
        const rows = Array.from(table?.querySelectorAll('tbody tr') || []);

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        input.addEventListener('input', () => {
            const query = input.value.trim().toLowerCase();

            rows.forEach((row) => {
                const text = row.textContent.toLowerCase();
                row.hidden = query !== '' && !text.includes(query);
            });
        });
    });
}
