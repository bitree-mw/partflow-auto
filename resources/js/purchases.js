import { initTableSearch } from './modules/table-search';

initTableSearch();

const parseAmount = (value) => {
    const normalized = String(value ?? '').replace(/[^\d.-]/g, '');
    const amount = Number.parseFloat(normalized);

    return Number.isFinite(amount) ? amount : 0;
};

const formatMoney = (value, currency) => {
    const hasDecimal = Math.abs(value % 1) > 0;

    return `${currency} ${value.toLocaleString('en-US', {
        minimumFractionDigits: hasDecimal ? 2 : 0,
        maximumFractionDigits: 2,
    })}`;
};

document.querySelectorAll('[data-purchase-form]').forEach((form) => {
    const currency = form.dataset.purchaseCurrency || 'MWK';
    const lines = form.querySelector('[data-purchase-lines]');
    const template = form.querySelector('[data-purchase-line-template]');
    const addButton = form.querySelector('[data-add-purchase-line]');
    const amountPaid = form.querySelector('[data-purchase-amount-paid]');
    const subtotalTarget = form.querySelector('[data-purchase-subtotal]');
    const paidTarget = form.querySelector('[data-purchase-paid]');
    const balanceTarget = form.querySelector('[data-purchase-balance]');
    let nextLineIndex = lines?.querySelectorAll('[data-purchase-line]').length ?? 0;

    if (! lines) {
        return;
    }

    const getLineTotal = (line) => {
        const quantity = parseAmount(line.querySelector('[data-purchase-quantity]')?.value);
        const unitCost = parseAmount(line.querySelector('[data-purchase-unit-cost]')?.value);

        return quantity * unitCost;
    };

    const updateLineNumbers = () => {
        const lineItems = [...lines.querySelectorAll('[data-purchase-line]')];

        lineItems.forEach((line, index) => {
            const number = line.querySelector('[data-purchase-line-number]');
            const removeButton = line.querySelector('[data-remove-purchase-line]');

            if (number) {
                number.textContent = index + 1;
            }

            if (removeButton) {
                removeButton.disabled = lineItems.length === 1;
            }
        });
    };

    const updateTotals = () => {
        let subtotal = 0;

        lines.querySelectorAll('[data-purchase-line]').forEach((line) => {
            const lineTotal = getLineTotal(line);
            const lineTotalTarget = line.querySelector('[data-purchase-line-total]');

            subtotal += lineTotal;

            if (lineTotalTarget) {
                lineTotalTarget.textContent = formatMoney(lineTotal, currency);
            }
        });

        const paid = parseAmount(amountPaid?.value);
        const balance = Math.max(subtotal - paid, 0);

        if (subtotalTarget) {
            subtotalTarget.textContent = formatMoney(subtotal, currency);
        }

        if (paidTarget) {
            paidTarget.textContent = formatMoney(paid, currency);
        }

        if (balanceTarget) {
            balanceTarget.textContent = formatMoney(balance, currency);
        }
    };

    addButton?.addEventListener('click', () => {
        if (! template) {
            return;
        }

        const wrapper = document.createElement('template');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextLineIndex));
        nextLineIndex += 1;

        const line = wrapper.content.firstElementChild;

        if (! line) {
            return;
        }

        lines.appendChild(line);
        updateLineNumbers();
        updateTotals();
        line.querySelector('select, input')?.focus();
    });

    form.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-purchase-line]');

        if (! removeButton) {
            return;
        }

        const lineItems = lines.querySelectorAll('[data-purchase-line]');

        if (lineItems.length <= 1) {
            return;
        }

        removeButton.closest('[data-purchase-line]')?.remove();
        updateLineNumbers();
        updateTotals();
    });

    form.addEventListener('input', (event) => {
        if (
            event.target.matches('[data-purchase-quantity]') ||
            event.target.matches('[data-purchase-unit-cost]') ||
            event.target.matches('[data-purchase-amount-paid]')
        ) {
            updateTotals();
        }
    });

    updateLineNumbers();
    updateTotals();
});
