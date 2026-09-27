const ENHANCED_ATTR = 'data-searchable-select-enhanced';
const SEARCHABLE_ATTR = 'data-searchable-select';
const PAGE_SIZE = 10;
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

function shouldEnhance(select) {
    return select.hasAttribute(SEARCHABLE_ATTR) || visibleOptions(select).filter((option) => option.value !== '').length > PAGE_SIZE;
}

function createOptionButton(option, select, onSelect) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'app-combobox-option';
    button.dataset.value = option.value;
    button.setAttribute('role', 'option');
    button.setAttribute('aria-selected', String(option.selected));
    button.textContent = optionLabel(option);

    button.addEventListener('click', () => onSelect(option));

    return button;
}

function enhanceSelect(select) {
    if (enhancedSelects.has(select) || !shouldEnhance(select) || select.multiple ||
        Number(select.getAttribute('size') || 1) > 1 || select.closest('[data-searchable-select-skip]')) {
        return;
    }

    select.setAttribute(ENHANCED_ATTR, 'true');
    enhancedSelects.add(select);
    select.classList.add('app-select-native');

    const wrapper = document.createElement('div');
    wrapper.className = 'app-combobox';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'app-combobox-input app-combobox-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.labels?.[0]?.textContent?.trim() || placeholderFor(select));
    if (select.required) {
        trigger.setAttribute('aria-required', 'true');
    }

    const panel = document.createElement('div');
    panel.className = 'app-combobox-list';
    panel.hidden = true;

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'app-combobox-search';
    search.autocomplete = 'off';
    search.placeholder = 'Search options...';
    search.setAttribute('aria-label', `Search ${trigger.getAttribute('aria-label')}`);

    const optionsList = document.createElement('div');
    optionsList.className = 'app-combobox-options';
    optionsList.setAttribute('role', 'listbox');
    optionsList.id = `app-combobox-${Math.random().toString(36).slice(2)}`;
    trigger.setAttribute('aria-controls', optionsList.id);
    const more = document.createElement('div');
    panel.append(search, optionsList, more);

    select.after(wrapper);
    wrapper.append(select, trigger, panel);

    function syncTrigger() {
        trigger.disabled = select.disabled;
        trigger.textContent = selectedLabel(select) || placeholderFor(select);

        if (select.disabled) {
            close();
        }
    }

    let visibleLimit = PAGE_SIZE;
    let currentQuery = '';

    function render(query = '', resetLimit = true) {
        currentQuery = query;

        if (resetLimit) {
            visibleLimit = PAGE_SIZE;
        }

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

        optionsList.replaceChildren();
        more.replaceChildren();

        const blankOption = emptyOption(select);

        if (blankOption && (!normalizedQuery || optionLabel(blankOption).toLowerCase().includes(normalizedQuery))) {
            optionsList.append(createOptionButton(blankOption, select, choose));
        }

        if (options.length === 0 && optionsList.children.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'app-combobox-empty';
            empty.textContent = 'No matches';
            optionsList.append(empty);
            return;
        }

        options.slice(0, visibleLimit).forEach((option) => {
            optionsList.append(createOptionButton(option, select, choose));
        });

        if (options.length > visibleLimit) {
            const remaining = options.length - visibleLimit;
            const loadMore = document.createElement('button');
            loadMore.type = 'button';
            loadMore.className = 'app-combobox-load-more';
            loadMore.textContent = `Show ${Math.min(PAGE_SIZE, remaining)} more (${remaining} remaining)`;
            loadMore.addEventListener('mousedown', (event) => event.preventDefault());
            loadMore.addEventListener('click', () => {
                visibleLimit += PAGE_SIZE;
                render(currentQuery, false);
                more.querySelector('.app-combobox-load-more')?.focus();
            });
            more.append(loadMore);
        }
    }

    function open() {
        if (select.disabled) {
            return;
        }

        search.value = '';
        render();
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        search.focus();
    }

    function close() {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    function choose(option) {
        select.value = option.value;
        syncTrigger();
        close();
        select.dispatchEvent(new Event('change', { bubbles: true }));
        trigger.focus();
    }

    function highlightedOption() {
        return optionsList.querySelector('.app-combobox-option.highlighted');
    }

    function highlight(offset) {
        const options = Array.from(optionsList.querySelectorAll('.app-combobox-option'));

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

    trigger.addEventListener('click', () => {
        if (panel.hidden) {
            open();
        } else {
            close();
        }
    });

    trigger.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            open();
            highlight(event.key === 'ArrowDown' ? 1 : -1);
        }
    });

    search.addEventListener('input', () => render(search.value));
    panel.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight(1);
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(-1);
        }

        if (event.key === 'Enter') {
            const option = highlightedOption() || optionsList.querySelector('.app-combobox-option');

            if (option && !panel.hidden) {
                event.preventDefault();
                option.click();
            }
        }

        if (event.key === 'Escape') {
            close();
            trigger.focus();
        }
    });

    wrapper.addEventListener('focusout', (event) => {
        if (!wrapper.contains(event.relatedTarget)) {
            close();
        }
    });

    select.addEventListener('change', () => {
        syncTrigger();
        if (!panel.hidden) {
            render(search.value);
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!wrapper.contains(event.target)) {
            close();
        }
    });

    syncTrigger();
}

function enhanceWithin(root) {
    if (root instanceof HTMLSelectElement) {
        enhanceSelect(root);
        return;
    }

    root.querySelectorAll?.('select').forEach(enhanceSelect);
}

export function initSearchableSelects() {
    enhanceWithin(document);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) {
                    enhanceWithin(node);

                    if (node instanceof HTMLOptionElement && node.parentElement instanceof HTMLSelectElement) {
                        enhanceSelect(node.parentElement);
                    }
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
}
