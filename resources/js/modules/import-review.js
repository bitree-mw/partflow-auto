// Parts import review: the duplicate-rows dialog, and bulk choices for rows that look like existing parts.

// Opens by itself when duplicates are found (not right after deleting some). Merging is the default,
// so closing it - or "Merge all by increment" - leaves the merge in place.
export function initImportDuplicatesDialog() {
    const dialog = document.querySelector('[data-import-duplicates-dialog]');

    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const open = () => {
        if (!dialog.open) {
            dialog.showModal();
        }
    };

    document.querySelectorAll('[data-open-import-duplicates]').forEach((button) => button.addEventListener('click', open));
    dialog.querySelectorAll('[data-close-import-duplicates]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    if (dialog.hasAttribute('data-open-on-load')) {
        open();
    }
}

// Suggestion chips fill the part type field. Typed choices only count once applied (the server rebuilds
// the review), so posting waits until they are applied or cleared.
export function initImportTypeMapping() {
    const inputs = [...document.querySelectorAll('[data-import-type-input]')];
    const postButton = document.querySelector('[data-import-confirm-form] button[type="submit"]');

    if (!inputs.length) {
        return;
    }

    const hint = document.createElement('span');
    hint.className = 'import-post-hint';
    hint.textContent = 'Apply or clear the part type choices before posting.';
    hint.hidden = true;
    postButton?.insertAdjacentElement('beforebegin', hint);

    const refresh = () => {
        const pending = inputs.some((input) => input.value.trim() !== '');

        if (postButton) {
            postButton.disabled = pending;
        }
        hint.hidden = !pending;
    };

    document.querySelectorAll('[data-import-type-suggestion]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const input = chip.closest('.import-new-type-choice')?.querySelector('[data-import-type-input]');

            if (input) {
                input.value = chip.dataset.importTypeSuggestion;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });

    inputs.forEach((input) => input.addEventListener('input', refresh));
    refresh();
}

// "best" picks each row's first offered part (the closest match); "new" creates every one as a new part.
export function initImportMergeChoices() {
    const selects = [...document.querySelectorAll('[data-import-merge-select]')];

    if (!selects.length) {
        return;
    }

    document.querySelectorAll('[data-import-merge-all]').forEach((button) => {
        button.addEventListener('click', () => {
            selects.forEach((select) => {
                select.value = button.dataset.importMergeAll === 'new' ? 'new' : (select.options[1]?.value ?? 'new');
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });
}
