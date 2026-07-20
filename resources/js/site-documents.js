const stockMap = JSON.parse(document.querySelector('[data-site-stock-map]')?.textContent || '{}');

document.querySelectorAll('[data-site-document-form]').forEach((form) => {
    const mode = form.dataset.documentMode;
    const sourceSite = form.querySelector('[data-source-site]');
    const lines = form.querySelector('[data-site-document-lines]');
    const template = form.querySelector('[data-site-line-template]');
    const addButton = form.querySelector('[data-add-site-line]');
    let nextIndex = lines?.querySelectorAll('[data-site-document-line]').length ?? 0;

    if (!lines || !template) {
        return;
    }

    function lineSelection(line) {
        const siteId = sourceSite?.value || '';
        const productId = line.querySelector('[data-line-product]')?.value || '';

        return { siteId, productId };
    }

    function stockForLine(line) {
        const { siteId, productId } = lineSelection(line);

        if (!siteId || !productId) {
            return null;
        }

        return stockMap?.[siteId]?.[productId] || { available: 0, on_hand: 0 };
    }

    function refreshLine(line) {
        const hint = line.querySelector('[data-stock-hint]');
        const summary = line.querySelector('[data-stock-summary]');
        const quantity = line.querySelector('[data-line-quantity]');
        const countedQuantity = line.querySelector('[data-line-counted]');
        const adjustmentReason = line.querySelector('[data-adjustment-reason]');
        const adjustmentReasonHint = line.querySelector('[data-adjustment-reason-hint]');
        const { siteId, productId } = lineSelection(line);
        const stock = stockForLine(line);
        const hasSelection = Boolean(siteId && productId);

        if (!hasSelection) {
            if (hint) {
                hint.textContent = mode === 'transfer' ? 'Select source and part' : 'Select site and part';
            }

            if (summary) {
                summary.textContent = mode === 'transfer'
                    ? 'Select source and part to view stock'
                    : 'Select site and part to view system stock';
            }

            if (quantity) {
                quantity.removeAttribute('max');
            }

            if (adjustmentReason) {
                adjustmentReason.required = false;
            }

            if (adjustmentReasonHint) {
                adjustmentReasonHint.textContent = 'Required when counted stock differs from system stock';
            }

            return;
        }

        if (hint) {
            hint.textContent = mode === 'transfer'
                ? `${stock.available} available at source`
                : `${stock.on_hand} in system stock`;
        }

        if (summary) {
            summary.textContent = mode === 'transfer'
                ? `Source stock: ${stock.available} available (${stock.on_hand} on hand)`
                : `System stock: ${stock.on_hand} on hand (${stock.available} available)`;
        }

        if (quantity) {
            if (mode === 'transfer') {
                quantity.max = stock.available;
            } else {
                quantity.removeAttribute('max');
            }
        }

        if (mode === 'stock-take' && adjustmentReason) {
            const counted = Number.parseInt(countedQuantity?.value ?? '', 10);
            const hasCount = Number.isFinite(counted);
            const variance = hasCount ? counted - Number(stock.on_hand) : 0;

            adjustmentReason.required = hasCount && variance !== 0;

            if (adjustmentReasonHint) {
                adjustmentReasonHint.textContent = variance === 0
                    ? 'No adjustment reason needed'
                    : `Required for ${variance > 0 ? '+' : ''}${variance} stock adjustment`;
            }
        }
    }

    function refreshLines() {
        const lineItems = Array.from(lines.querySelectorAll('[data-site-document-line]'));

        lineItems.forEach((line, index) => {
            const number = line.querySelector('[data-site-line-number]');
            const removeButton = line.querySelector('[data-remove-site-line]');

            if (number) {
                number.textContent = index + 1;
            }

            if (removeButton) {
                removeButton.disabled = lineItems.length === 1;
            }

            refreshLine(line);
        });
    }

    addButton?.addEventListener('click', () => {
        const wrapper = document.createElement('div');

        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        lines.append(...wrapper.childNodes);
        nextIndex += 1;
        refreshLines();
        lines.querySelector('[data-site-document-line]:last-child select')?.focus();
    });

    lines.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
        const removeButton = target?.closest('[data-remove-site-line]');

        if (!removeButton) {
            return;
        }

        const lineItems = lines.querySelectorAll('[data-site-document-line]');

        if (lineItems.length === 1) {
            return;
        }

        removeButton.closest('[data-site-document-line]')?.remove();
        refreshLines();
    });

    lines.addEventListener('change', (event) => {
        if (event.target.matches('[data-line-product], [data-line-quantity], [data-line-counted]')) {
            refreshLines();
        }
    });

    lines.addEventListener('input', (event) => {
        if (event.target.matches('[data-line-counted]')) {
            refreshLine(event.target.closest('[data-site-document-line]'));
        }
    });

    sourceSite?.addEventListener('change', refreshLines);
    refreshLines();
});
