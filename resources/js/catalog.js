import { initImportFileCheck } from './modules/import-file-check';
import { initImportConfirmProgress } from './modules/import-progress';
import { initImportDuplicatesDialog, initImportMergeChoices, initImportTypeMapping } from './modules/import-review';
import { initTableSearch } from './modules/table-search';

initTableSearch();
initImportFileCheck();
initImportConfirmProgress();
initImportMergeChoices();
initImportDuplicatesDialog();
initImportTypeMapping();

const carModelPickers = new WeakSet();
const productTypePickers = new WeakSet();

function initCarModelPicker(picker) {
    if (carModelPickers.has(picker)) {
        return;
    }

    const endpoint = picker.dataset.endpoint;
    const valueInput = picker.querySelector('[data-car-model-value]');
    const trigger = picker.querySelector('[data-car-model-trigger]');
    const panel = picker.querySelector('[data-car-model-panel]');
    const searchInput = picker.querySelector('[data-car-model-search]');
    const results = picker.querySelector('[data-car-model-results]');

    if (!endpoint || !valueInput || !trigger || !panel || !searchInput || !results) {
        return;
    }

    carModelPickers.add(picker);
    panel.hidden = true;
    results.replaceChildren();
    searchInput.value = '';
    if (!valueInput.value) {
        trigger.textContent = trigger.dataset.placeholder || 'Select vehicle';
    }
    panel.id = `car-model-picker-${Math.random().toString(36).slice(2)}`;
    trigger.setAttribute('aria-controls', panel.id);

    let abortController = null;
    let searchTimer = null;
    let currentQuery = '';
    let currentPage = 1;

    function closeResults() {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    function removeLoadMoreButton() {
        results.querySelector('[data-car-model-load-more]')?.remove();
    }

    function appendOption(item) {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'async-picker-option';
        option.setAttribute('role', 'option');
        option.textContent = item.label;

        option.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        option.addEventListener('click', () => {
            valueInput.value = item.id;
            trigger.textContent = item.label || trigger.dataset.placeholder || 'Select vehicle';
            closeResults();
            valueInput.dispatchEvent(new Event('change', { bubbles: true }));
            trigger.focus();
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
            if (!currentQuery.trim() && picker.dataset.emptyLabel) {
                appendOption({ id: '', label: picker.dataset.emptyLabel });
            }
        }

        if (!items.length && !append && results.children.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'async-picker-empty';
            empty.textContent = 'No matching vehicle variants';
            results.appendChild(empty);
            return;
        }

        items.forEach(appendOption);
        appendLoadMoreButton(meta);

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
            if (cleanQuery !== searchInput.value.trim()) {
                return;
            }
            renderResults(payload.data || [], payload.meta || {}, append);
        } catch (error) {
            if (error.name !== 'AbortError') {
                renderResults([], {}, append);
            }
        }
    }

    function queueSearch() {
        window.clearTimeout(searchTimer);
        results.innerHTML = '<div class="async-picker-empty">Searching...</div>';
        searchTimer = window.setTimeout(() => searchModels(searchInput.value, 1), 160);
    }

    trigger.addEventListener('click', () => {
        if (!panel.hidden) {
            closeResults();
            return;
        }

        searchInput.value = '';
        results.innerHTML = '<div class="async-picker-empty">Loading...</div>';
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        searchInput.focus();
        searchModels('', 1);
    });

    searchInput.addEventListener('input', queueSearch);

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeResults();
            trigger.focus();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            const options = Array.from(results.querySelectorAll('.async-picker-option'));
            if (options.length) {
                event.preventDefault();
                const current = options.findIndex((option) => option.classList.contains('highlighted'));
                const next = current < 0
                    ? (event.key === 'ArrowDown' ? 0 : options.length - 1)
                    : (current + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options.forEach((option) => option.classList.remove('highlighted'));
                options[next].classList.add('highlighted');
                options[next].scrollIntoView({ block: 'nearest' });
            }
        } else if (event.key === 'Enter') {
            const firstOption = results.querySelector('.async-picker-option.highlighted, .async-picker-option');
            if (firstOption) {
                event.preventDefault();
                firstOption.click();
            }
        }
    });

    picker.addEventListener('focusout', (event) => {
        if (!picker.contains(event.relatedTarget)) {
            closeResults();
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!picker.contains(event.target)) {
            closeResults();
        }
    });
}

function initCarModelPickers(root = document) {
    root.querySelectorAll?.('[data-car-model-picker]').forEach(initCarModelPicker);
}

initCarModelPickers();

const productSellingPrice = document.querySelector('[data-product-selling-price]');
const productMinimumPrice = document.querySelector('[data-product-minimum-price]');

if (productSellingPrice && productMinimumPrice) {
    const suggestedMinimum = () => Math.round((Number(productSellingPrice.value) || 0) * 80) / 100;
    const initialMinimum = Number(productMinimumPrice.value);
    const initialSuggestedMinimum = suggestedMinimum();
    let minimumWasManuallyEdited = productMinimumPrice.value !== ''
        && Math.abs(initialMinimum - initialSuggestedMinimum) > 0.009;

    const syncSuggestedMinimum = () => {
        if (!minimumWasManuallyEdited) {
            productMinimumPrice.value = suggestedMinimum().toFixed(2);
        }
    };

    productMinimumPrice.addEventListener('input', () => {
        minimumWasManuallyEdited = productMinimumPrice.value !== '';
    });
    productSellingPrice.addEventListener('input', syncSuggestedMinimum);
    syncSuggestedMinimum();
}

// Left and right parts: each side gets its own generated code, so the manual code is switched off.
const createSides = document.querySelector('[data-product-create-sides]');

if (createSides) {
    const productCode = document.querySelector('#product_code');
    const saveButton = document.querySelector('[data-product-save-sides-label]');

    const syncSides = () => {
        if (productCode) {
            productCode.disabled = createSides.checked;
            productCode.placeholder = createSides.checked
                ? 'Generated for each side'
                : 'Auto generated, e.g. EOIL-CAST-MWI-001';
        }

        if (saveButton) {
            saveButton.textContent = createSides.checked
                ? saveButton.dataset.productSaveSidesLabel
                : saveButton.dataset.productSaveLabel;
        }
    };

    createSides.addEventListener('change', syncSides);
    syncSides();
}

function initProductTypePicker(picker) {
    if (productTypePickers.has(picker)) {
        return;
    }

    const endpoint = picker.dataset.endpoint;
    const valueInput = picker.querySelector('[data-product-type-value]');
    const trigger = picker.querySelector('[data-product-type-trigger]');
    const panel = picker.querySelector('[data-product-type-panel]');
    const searchInput = picker.querySelector('[data-product-type-search]');
    const results = picker.querySelector('[data-product-type-results]');

    if (!endpoint || !valueInput || !trigger || !panel || !searchInput || !results) {
        return;
    }

    productTypePickers.add(picker);
    panel.hidden = true;
    results.replaceChildren();
    searchInput.value = '';
    if (!valueInput.value) {
        trigger.textContent = trigger.dataset.placeholder || 'Select product type';
    }
    panel.id = `product-type-picker-${Math.random().toString(36).slice(2)}`;
    trigger.setAttribute('aria-controls', panel.id);

    let abortController = null;
    let searchTimer = null;
    let currentQuery = '';
    let currentPage = 1;

    function closeResults() {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    function removeLoadMoreButton() {
        results.querySelector('[data-product-type-load-more]')?.remove();
    }

    function appendOption(item) {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'async-picker-option';
        option.setAttribute('role', 'option');
        option.textContent = item.label;

        option.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        option.addEventListener('click', () => {
            valueInput.value = item.id;
            trigger.textContent = item.label || trigger.dataset.placeholder || 'Select product type';
            closeResults();
            valueInput.dispatchEvent(new Event('change', { bubbles: true }));
            trigger.focus();
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
        button.dataset.productTypeLoadMore = 'true';

        button.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        button.addEventListener('click', () => {
            searchProductTypes(currentQuery, meta.next_page || currentPage + 1, true);
        });

        results.appendChild(button);
    }

    function renderResults(items, meta = {}, append = false) {
        if (append) {
            removeLoadMoreButton();
        } else {
            results.replaceChildren();
            if (!currentQuery.trim() && picker.dataset.emptyLabel) {
                appendOption({ id: '', label: picker.dataset.emptyLabel });
            }
        }

        if (!items.length && !append && results.children.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'async-picker-empty';
            empty.textContent = 'No matching product types';
            results.appendChild(empty);
            return;
        }

        items.forEach(appendOption);
        appendLoadMoreButton(meta);

    }

    async function searchProductTypes(query = '', page = 1, append = false) {
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
            if (cleanQuery !== searchInput.value.trim()) {
                return;
            }
            renderResults(payload.data || [], payload.meta || {}, append);
        } catch (error) {
            if (error.name !== 'AbortError') {
                renderResults([], {}, append);
            }
        }
    }

    function queueSearch() {
        window.clearTimeout(searchTimer);
        results.innerHTML = '<div class="async-picker-empty">Searching...</div>';
        searchTimer = window.setTimeout(() => searchProductTypes(searchInput.value, 1), 160);
    }

    trigger.addEventListener('click', () => {
        if (!panel.hidden) {
            closeResults();
            return;
        }

        searchInput.value = '';
        results.innerHTML = '<div class="async-picker-empty">Loading...</div>';
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        searchInput.focus();
        searchProductTypes('', 1);
    });

    searchInput.addEventListener('input', queueSearch);

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeResults();
            trigger.focus();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            const options = Array.from(results.querySelectorAll('.async-picker-option'));
            if (options.length) {
                event.preventDefault();
                const current = options.findIndex((option) => option.classList.contains('highlighted'));
                const next = current < 0
                    ? (event.key === 'ArrowDown' ? 0 : options.length - 1)
                    : (current + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options.forEach((option) => option.classList.remove('highlighted'));
                options[next].classList.add('highlighted');
                options[next].scrollIntoView({ block: 'nearest' });
            }
        } else if (event.key === 'Enter') {
            const firstOption = results.querySelector('.async-picker-option.highlighted, .async-picker-option');
            if (firstOption) {
                event.preventDefault();
                firstOption.click();
            }
        }
    });

    picker.addEventListener('focusout', (event) => {
        if (!picker.contains(event.relatedTarget)) {
            closeResults();
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!picker.contains(event.target)) {
            closeResults();
        }
    });
}

function initProductTypePickers(root = document) {
    root.querySelectorAll?.('[data-product-type-picker]').forEach(initProductTypePicker);
}

initProductTypePickers();

document.querySelectorAll('[data-live-product-filters]').forEach((form) => {
    const searchInput = form.querySelector('input[name="search"]');
    const panel = form.closest('[data-catalogue-panel]');
    const results = panel?.querySelector('[data-catalogue-results]');
    const status = panel?.querySelector('[data-catalogue-search-status]');
    if (!results || !status) return;
    let submitTimer = null;
    let request = null;
    let version = 0;

    async function loadResults(url) {
        const currentVersion = ++version;
        request?.abort();
        request = new AbortController();
        results.setAttribute('aria-busy', 'true');
        status.textContent = 'Searching…';
        try {
            const response = await fetch(url, {
                signal: request.signal,
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok || response.redirected) throw new Error('Search failed');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.querySelector('[data-catalogue-results]');
            if (!updated) throw new Error('Missing results');
            if (currentVersion !== version) return;
            results.replaceChildren(...updated.childNodes);
            const sizes = page.querySelector('[data-live-product-filters] .page-size-controls');
            if (sizes) form.querySelector('.page-size-controls')?.replaceWith(sizes);
            form.querySelector('[name="per_page"]').value = url.searchParams.get('per_page') || '10';
            window.history.replaceState(null, '', url);
            status.textContent = 'Results updated.';
        } catch (error) {
            if (error.name !== 'AbortError' && currentVersion === version) {
                status.textContent = 'Could not update results. Check your connection and press Filter to retry.';
            }
        } finally {
            if (currentVersion === version) results.removeAttribute('aria-busy');
        }
    }

    function filterUrl() {
        const url = new URL(form.action, window.location.origin);
        new FormData(form).forEach((value, key) => {
            if (value !== '') url.searchParams.set(key, value);
        });
        for (const key of ['sort', 'direction']) {
            const value = new URL(window.location.href).searchParams.get(key);
            if (value) url.searchParams.set(key, value);
        }
        return url;
    }

    function submitFilters(delay = 0) {
        window.clearTimeout(submitTimer);
        request?.abort();
        version++;
        submitTimer = window.setTimeout(() => loadResults(filterUrl()), delay);
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitFilters();
    });
    searchInput?.addEventListener('input', () => submitFilters(200));

    form.querySelectorAll('select, input[type="hidden"][name="product_type_id"]').forEach((control) => {
        control.addEventListener('change', () => submitFilters());
    });
    panel.addEventListener('click', (event) => {
        const link = event.target.closest('.pagination-wrap a, th a, .page-size-controls a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        window.clearTimeout(submitTimer);
        const url = filterUrl();
        const target = new URL(link.href);
        for (const key of ['page', 'per_page', 'sort', 'direction']) {
            if (target.searchParams.has(key)) url.searchParams.set(key, target.searchParams.get(key));
        }
        if (link.closest('.page-size-controls')) url.searchParams.delete('page');
        loadResults(url);
    });
});

document.querySelectorAll('[data-product-brand]').forEach((brandSelect) => {
    const form = brandSelect.closest('form');
    const originInput = form?.querySelector('[data-product-origin]');

    if (!originInput) {
        return;
    }

    function syncOrigin() {
        const selectedBrand = brandSelect.selectedOptions[0];
        const isUnknown = !selectedBrand?.value || selectedBrand.dataset.unknown === '1';

        originInput.readOnly = true;
        originInput.setAttribute('aria-readonly', 'true');

        if (isUnknown) {
            originInput.value = '';
            originInput.placeholder = 'Select a brand with a country';
            return;
        }

        originInput.value = selectedBrand.dataset.country || '';
        originInput.placeholder = selectedBrand.dataset.country
            ? 'Derived from selected brand'
            : 'Brand country is not set';
    }

    brandSelect.addEventListener('change', syncOrigin);
    syncOrigin();
});

document.querySelectorAll('[data-compatibility-list]').forEach((list) => {
    const addButton = document.querySelector('[data-add-compatibility-variant]');

    function bindRemove(button) {
        button.addEventListener('click', () => {
            const rows = list.querySelectorAll('.compatibility-variant-row');

            if (rows.length === 1) {
                rows[0].querySelector('[data-car-model-trigger]')?.focus();
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
        });

        row.querySelectorAll('[data-car-model-panel]').forEach((panel) => {
            panel.hidden = true;
        });

        const input = row.querySelector('[data-car-model-trigger]');
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
