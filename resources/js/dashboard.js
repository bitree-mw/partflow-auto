const liveDashboard = document.querySelector('[data-dashboard-live]');
const refreshInterval = 15000;
let refreshController = null;
let lastRefreshAt = 0;

const updateMetric = (selector, value, change) => {
    const card = document.querySelector(selector);

    if (!card) {
        return;
    }

    const valueTarget = card.querySelector('[data-dashboard-metric-value]');
    const changeTarget = card.querySelector('[data-dashboard-metric-change]');

    if (valueTarget) {
        valueTarget.textContent = value;
    }

    if (changeTarget) {
        changeTarget.textContent = change;
    }
};

const refreshDashboard = async ({ force = false } = {}) => {
    if (!liveDashboard?.dataset.dashboardLiveUrl || document.hidden) {
        return;
    }

    if (!force && Date.now() - lastRefreshAt < 2000) {
        return;
    }

    refreshController?.abort();
    refreshController = new AbortController();

    try {
        const response = await fetch(liveDashboard.dataset.dashboardLiveUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            cache: 'no-store',
            signal: refreshController.signal,
        });

        if (!response.ok) {
            return;
        }

        const snapshot = await response.json();
        updateMetric('[data-dashboard-today-sales]', snapshot.today_sales, snapshot.today_sales_change);
        updateMetric('[data-dashboard-today-profit]', snapshot.today_profit, snapshot.today_profit_change);

        const averageSale = document.querySelector('[data-dashboard-average-sale]');
        if (averageSale) {
            averageSale.textContent = snapshot.average_sale;
        }

        lastRefreshAt = Date.now();
    } catch (error) {
        if (error.name !== 'AbortError') {
            lastRefreshAt = Date.now();
        }
    }
};

if (liveDashboard) {
    window.setInterval(refreshDashboard, refreshInterval);
    window.addEventListener('focus', () => refreshDashboard({ force: true }));
    window.addEventListener('storage', (event) => {
        if (event.key === 'partflow:sale-completed') {
            refreshDashboard({ force: true });
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshDashboard({ force: true });
        }
    });
}
