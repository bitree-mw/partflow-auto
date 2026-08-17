const filterPanel = document.querySelector('[data-report-filter-panel]');

filterPanel?.classList.add('is-ready');

const periodSelect = filterPanel?.querySelector('[data-report-period]');
const dateFromInput = filterPanel?.querySelector('[data-report-date-from]');
const dateToInput = filterPanel?.querySelector('[data-report-date-to]');

const toDateInputValue = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const rangeForPeriod = (period) => {
    const today = new Date();
    const from = new Date(today);

    if (period === 'week') {
        const weekday = today.getDay() || 7;
        from.setDate(today.getDate() - weekday + 1);
    } else if (period === 'month') {
        from.setDate(1);
    } else if (period === 'year') {
        from.setMonth(0, 1);
    }

    return { from, to: today };
};

periodSelect?.addEventListener('change', () => {
    if (periodSelect.value === 'custom' || !dateFromInput || !dateToInput) {
        return;
    }

    const range = rangeForPeriod(periodSelect.value);
    dateFromInput.value = toDateInputValue(range.from);
    dateToInput.value = toDateInputValue(range.to);
});

[dateFromInput, dateToInput].forEach((input) => input?.addEventListener('change', () => {
    if (periodSelect) {
        periodSelect.value = 'custom';
    }
}));
