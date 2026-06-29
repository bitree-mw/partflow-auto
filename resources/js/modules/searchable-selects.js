const ENHANCED_ATTR = 'data-searchable-select-enhanced';
const SEARCHABLE_ATTR = 'data-searchable-select';
const enhancedSelects = new WeakSet();

function visibleOptions(select) {
    return Array.from(select.options).filter((option) => !option.hidden && !option.disabled);
}

function optionLabel(option) {
    return option?.textContent?.trim() || '';
}

function selectedLabel(select) {
    const option = select.selectedOptions[0];

    if (!option || option.value === '') {
        return '';
    }

    return optionLabel(option);
}

function emptyOption(select) {
    return Array.from(select.options).find((option) => option.value === '');
}

function placeholderFor(select) {
    return optionLabel(emptyOption(select)) || select.getAttribute('aria-label') || 'Select item';
}

function createOptionButton(option, select, input, list) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'app-combobox-option';
    button.dataset.value = option.value;
    button.setAttribute('role', 'option');
    button.setAttribute('aria-selected', String(option.selected));
    button.textContent = optionLabel(option);

    button.addEventListener('mousedown', (event) => {
        event.preventDefault();
    });

    button.addEventListener('click', () => {
        select.value = option.value;
        input.value = option.value === '' ? '' : optionLabel(option);
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });

    return button;
}

function enhanceSelect(select) {
    if (enhancedSelects.has(select)) {
        return;
    }

    if (!select.hasAttribute(SEARCHABLE_ATTR)) {
        if (select.hasAttribute(ENHANCED_ATTR)) {
            const wrapper = select.closest('.app-combobox');

            if (wrapper) {
                wrapper.after(select);
                wrapper.remove();
            }

            select.removeAttribute(ENHANCED_ATTR);
            select.classList.remove('app-select-native');
            enhancedSelects.delete(select);
        }

        return;
    }

    if (select.hasAttribute(ENHANCED_ATTR)) {
        const wrapper = select.closest('.app-combobox');

        if (wrapper) {
            wrapper.after(select);
            wrapper.remove();
        }

        select.removeAttribute(ENHANCED_ATTR);
        select.classList.remove('app-select-native');
    }

    if (
        select.multiple ||
        Number(select.getAttribute('size') || 1) > 1 ||
        select.closest('[data-searchable-select-skip]')
    ) {
        return;
    }

    select.setAttribute(ENHANCED_ATTR, 'true');
    enhancedSelects.add(select);
    select.classList.add('app-select-native');

    const wrapper = document.createElement('div');
    wrapper.className = 'app-combobox';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'app-combobox-input';
    input.autocomplete = 'off';
    input.placeholder = placeholderFor(select);
    input.value = selectedLabel(select);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    const list = document.createElement('div');
    list.className = 'app-combobox-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;

    select.after(wrapper);
    wrapper.append(select, input, list);

    function syncInputState() {
        input.disabled = select.disabled;
        input.placeholder = placeholderFor(select);
    }

    function render(query = '') {
        const normalizedQuery = query.trim().toLowerCase();
        const options = visibleOptions(select)
            .filter((option) => option.value !== '')
            .filter((option) => {
                const label = optionLabel(option);

                if (!normalizedQuery) {
                    return true;
                }

                return label.toLowerCase().includes(normalizedQuery);
            });

        list.replaceChildren();

        if (options.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'app-combobox-empty';
            empty.textContent = 'No matches';
            list.append(empty);
            return;
        }

        options.forEach((option) => {
            list.append(createOptionButton(option, select, input, list));
        });
    }

    function open(showSuggestions = true) {
        render(showSuggestions ? '' : input.value);
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    }

    function highlightedOption() {
        return list.querySelector('.app-combobox-option.highlighted');
    }

    function highlight(offset) {
        const options = Array.from(list.querySelectorAll('.app-combobox-option'));

        if (options.length === 0) {
            return;
        }

        const currentIndex = options.indexOf(highlightedOption());
        const nextIndex = currentIndex === -1
            ? (offset > 0 ? 0 : options.length - 1)
            : (currentIndex + offset + options.length) % options.length;

        options.forEach((option) => option.classList.remove('highlighted'));
        options[nextIndex].classList.add('highlighted');
        options[nextIndex].scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('focus', () => {
        open(true);
        window.setTimeout(() => input.select(), 0);
    });

    input.addEventListener('click', () => {
        input.select();
    });

    input.addEventListener('mouseup', (event) => {
        event.preventDefault();
    });

    input.addEventListener('input', () => {
        const empty = emptyOption(select);

        if (input.value.trim() === '' && empty) {
            select.value = empty.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        open(false);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (list.hidden) {
                open();
            }
            highlight(1);
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) {
                open();
            }
            highlight(-1);
        }

        if (event.key === 'Enter') {
            const option = highlightedOption() || list.querySelector('.app-combobox-option');

            if (option && !list.hidden) {
                event.preventDefault();
                option.click();
            }
        }

        if (event.key === 'Escape') {
            close();
            input.value = selectedLabel(select);
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            close();
            input.value = selectedLabel(select);
        }, 120);
    });

    select.addEventListener('change', () => {
        syncInputState();
        input.value = selectedLabel(select);
        render('');
    });

    syncInputState();
}

function enhanceWithin(root) {
    if (root instanceof HTMLSelectElement) {
        enhanceSelect(root);
        return;
    }

    root.querySelectorAll?.(`select[${SEARCHABLE_ATTR}]`).forEach(enhanceSelect);
}

export function initSearchableSelects() {
    enhanceWithin(document);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) {
                    enhanceWithin(node);
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
}
