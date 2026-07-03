import { initTableSearch } from './modules/table-search';

initTableSearch();

const carModelPickers = new WeakSet();

function initCarModelPicker(picker) {
    if (carModelPickers.has(picker)) {
        return;
    }

    const endpoint = picker.dataset.endpoint;
    const valueInput = picker.querySelector('[data-car-model-value]');
    const searchInput = picker.querySelector('[data-car-model-search]');
    const results = picker.querySelector('[data-car-model-results]');

    if (!endpoint || !valueInput || !searchInput || !results) {
        return;
    }

    carModelPickers.add(picker);

    let abortController = null;
    let searchTimer = null;
    let currentQuery = '';
    let currentPage = 1;

    function closeResults() {
        results.hidden = true;
    }

    function removeLoadMoreButton() {
        results.querySelector('[data-car-model-load-more]')?.remove();
    }

    function appendOption(item) {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'async-picker-option';
        option.textContent = item.label;

        option.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        option.addEventListener('click', () => {
            valueInput.value = item.id;
            searchInput.value = item.label;
            closeResults();
        });

        results.appendChild(option);
    }

    function appendLoadMoreButton(meta) {
        if (!meta?.has_more) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'async-picker-more';
        button.textContent = 'Load more';
        button.dataset.carModelLoadMore = 'true';

        button.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        button.addEventListener('click', () => {
            searchModels(currentQuery, meta.next_page || currentPage + 1, true);
        });

        results.appendChild(button);
    }

    function renderResults(items, meta = {}, append = false) {
        if (append) {
            removeLoadMoreButton();
        } else {
            results.replaceChildren();
        }

        if (!items.length && !append) {
            const empty = document.createElement('div');
            empty.className = 'async-picker-empty';
            empty.textContent = 'No matching vehicle variants';
            results.appendChild(empty);
            results.hidden = false;
            return;
        }

        items.forEach(appendOption);
        appendLoadMoreButton(meta);

        results.hidden = false;
    }

    async function searchModels(query = '', page = 1, append = false) {
        abortController?.abort();
        abortController = new AbortController();
        currentQuery = query;
        currentPage = page;

        const url = new URL(endpoint, window.location.origin);
        const cleanQuery = query.trim();

        if (cleanQuery) {
            url.searchParams.set('search', cleanQuery);
        }

        url.searchParams.set('page', page);
        url.searchParams.set('per_page', 50);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            });

            if (!response.ok) {
                renderResults([], {}, append);
                return;
            }

            const payload = await response.json();
            renderResults(payload.data || [], payload.meta || {}, append);
        } catch (error) {
            if (error.name !== 'AbortError') {
                renderResults([], {}, append);
            }
        }
    }

    function queueSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => searchModels(searchInput.value, 1), 160);
    }

    searchInput.addEventListener('focus', () => {
        searchInput.select();
        searchModels(searchInput.value, 1);
    });

    searchInput.addEventListener('input', () => {
        valueInput.value = '';
        queueSearch();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeResults();
        }
    });

    searchInput.addEventListener('blur', () => {
        window.setTimeout(closeResults, 140);
    });
}

function initCarModelPickers(root = document) {
    root.querySelectorAll?.('[data-car-model-picker]').forEach(initCarModelPicker);
}

initCarModelPickers();

document.querySelectorAll('[data-compatibility-list]').forEach((list) => {
    const addButton = document.querySelector('[data-add-compatibility-variant]');

    function bindRemove(button) {
        button.addEventListener('click', () => {
            const rows = list.querySelectorAll('.compatibility-variant-row');

            if (rows.length === 1) {
                rows[0].querySelector('[data-car-model-search], input:not([type="hidden"]), select')?.focus();
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

        row.querySelectorAll('input, select, textarea').forEach((field) => {
            field.value = '';
        });

        row.querySelectorAll('[data-car-model-results]').forEach((results) => {
            results.replaceChildren();
            results.hidden = true;
        });

        const input = row.querySelector('[data-car-model-search], input:not([type="hidden"]), select');
        const removeButton = row.querySelector('[data-remove-compatibility-variant]');

        if (removeButton) {
            bindRemove(removeButton);
        }

        list.appendChild(row);
        initCarModelPickers(row);
        input?.focus();
    });
});

document.querySelectorAll('[data-car-make-select]').forEach((makeSelect) => {
    const form = makeSelect.closest('form');
    const modelSelect = form?.querySelector('[data-vehicle-model-select]');
    const yearInput = form?.querySelector('[data-model-year-input]');

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

    function syncYearFromModel() {
        if (!yearInput) {
            return;
        }

        const selectedOption = modelSelect.selectedOptions[0];
        const modelYear = selectedOption?.dataset.year || '';

        yearInput.value = modelYear;
    }

    makeSelect.addEventListener('change', syncModels);
    modelSelect.addEventListener('change', syncYearFromModel);
    syncModels();
    syncYearFromModel();
});
