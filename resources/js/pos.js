// POS UI interactions. Product search, site switching, cart editing, and checkout are API-backed.
let products = JSON.parse(document.querySelector('#pos-products-data')?.textContent || '[]');
let currentBranch = JSON.parse(document.querySelector('#pos-current-branch')?.textContent || '""');

const endpoints = JSON.parse(document.querySelector('#pos-endpoints-data')?.textContent || '{}');
const searchInput = document.querySelector('#part-search');
const vehicleFilter = document.querySelector('[data-pos-vehicle-filter]');
const productTypeFilter = document.querySelector('[data-pos-product-type-filter]');
const filters = Array.from(document.querySelectorAll('[data-pos-filter]'));
const clearSearch = document.querySelector('[data-pos-clear]');
const resetSearch = document.querySelector('[data-pos-reset]');
const productGrid = document.querySelector('.product-card-grid');
const resultCount = document.querySelector('[data-result-count]');
const suggestionsRow = document.querySelector('[data-suggestions-row]');
const suggestionsList = document.querySelector('[data-suggestions-list]');
const cartList = document.querySelector('[data-cart-list]');
const subtotalLabel = document.querySelector('[data-subtotal]');
const totalDueLabel = document.querySelector('[data-total-due]');
const cartPayload = document.querySelector('[data-cart-payload]');
const amountPaidInput = document.querySelector('[data-pos-amount-paid]');
const discountInput = document.querySelector('[data-pos-discount-amount]');
const discountPercentageLabel = document.querySelector('[data-discount-percentage]');
const discountLimitLabel = document.querySelector('[data-discount-limit]');
const discountFeedback = document.querySelector('[data-pos-discount-feedback]');
const saleTotalLabel = document.querySelector('[data-sale-total]');
const checkoutForm = document.querySelector('[data-pos-checkout-form]');
const completeSaleButton = document.querySelector('[data-complete-sale]');
const completeSaleTotalLabel = document.querySelector('[data-complete-sale-total]');
const siteSelector = document.querySelector('[data-pos-site-selector]');
const sourceSiteInput = document.querySelector('[data-pos-source-site-id]');

document.querySelectorAll('[data-close-pos]').forEach((button) => {
    button.addEventListener('click', () => {
        window.close();

        window.setTimeout(() => {
            if (! window.closed) {
                window.location.assign(button.dataset.fallbackUrl || '/');
            }
        }, 150);
    });
});

let cartItems = [];
let searchController = null;
let suggestionController = null;
let vehicleController = null;
let productTypeController = null;
let currentSaleTotal = 0;
let amountPaidEdited = false;
let selectedSiteId = siteSelector?.value || sourceSiteInput?.value || '';
let vehicleOptions = new Map();
const configuredMaximumDiscountPercentage = Math.max(0, Number(endpoints.maximumDiscountPercentage) || 0);

function formatCurrency(value) {
    return `MWK ${Math.round(value).toLocaleString('en-US')}`;
}

function parseCurrency(value) {
    return Number(String(value).replace(/[^\d.-]/g, '')) || 0;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function debounce(callback, wait = 250) {
    let timeoutId;

    return (...args) => {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => callback(...args), wait);
    };
}

function ensureDialogLayer() {
    let layer = document.querySelector('[data-pos-dialog-layer]');

    if (!layer) {
        layer = document.createElement('div');
        layer.className = 'pos-dialog-layer';
        layer.setAttribute('data-pos-dialog-layer', '');
        layer.hidden = true;
        document.body.appendChild(layer);
    }

    return layer;
}

function dismissPosDialog(layer, callback) {
    if (layer.classList.contains('is-closing')) {
        return;
    }

    layer.classList.add('is-closing');
    let finished = false;

    const finish = () => {
        if (finished) {
            return;
        }

        finished = true;
        layer.hidden = true;
        layer.classList.remove('is-closing');
        layer.innerHTML = '';
        document.body.classList.remove('pos-dialog-open');
        callback();
    };

    layer.addEventListener('animationend', finish, { once: true });
    window.setTimeout(finish, 220);
}

function showAppAlert(message, options = {}) {
    const layer = ensureDialogLayer();
    const title = options.title || 'Notice';
    const tone = options.tone || 'info';
    const titleId = `pos-dialog-title-${Date.now()}`;
    const previouslyFocused = document.activeElement;

    layer.innerHTML = `
        <section class="pos-dialog-card ${escapeHtml(tone)}" role="dialog" aria-modal="true" aria-labelledby="${titleId}">
            <div class="pos-dialog-mark" aria-hidden="true"></div>
            <h2 id="${titleId}">${escapeHtml(title)}</h2>
            <p>${escapeHtml(message)}</p>
            <div class="pos-dialog-actions">
                <button class="pos-dialog-primary" type="button" data-pos-dialog-ok>OK</button>
            </div>
        </section>
    `;
    layer.classList.remove('is-closing');
    layer.hidden = false;
    document.body.classList.add('pos-dialog-open');

    return new Promise((resolve) => {
        const okButton = layer.querySelector('[data-pos-dialog-ok]');

        const close = () => {
            document.removeEventListener('keydown', onKeydown);
            dismissPosDialog(layer, () => {
                previouslyFocused?.focus?.();
                resolve();
            });
        };

        const onKeydown = (event) => {
            if (event.key === 'Escape' || event.key === 'Enter') {
                event.preventDefault();
                close();
            }
        };

        okButton?.addEventListener('click', close, { once: true });
        document.addEventListener('keydown', onKeydown);
        okButton?.focus();
    });
}

function requestAdminPassword() {
    const layer = ensureDialogLayer();
    const titleId = `pos-dialog-title-${Date.now()}`;
    const previouslyFocused = document.activeElement;

    layer.innerHTML = `
        <section class="pos-dialog-card" role="dialog" aria-modal="true" aria-labelledby="${titleId}">
            <div class="pos-dialog-mark" aria-hidden="true"></div>
            <h2 id="${titleId}">Admin approval</h2>
            <p>Enter an admin password to change the selling site.</p>
            <form class="pos-dialog-form" data-pos-password-form>
                <input type="password" name="admin_password" autocomplete="current-password" aria-label="Admin password">
                <div class="pos-dialog-actions">
                    <button class="pos-dialog-secondary" type="button" data-pos-dialog-cancel>Cancel</button>
                    <button class="pos-dialog-primary" type="submit">Change site</button>
                </div>
            </form>
        </section>
    `;
    layer.classList.remove('is-closing');
    layer.hidden = false;
    document.body.classList.add('pos-dialog-open');

    return new Promise((resolve) => {
        const form = layer.querySelector('[data-pos-password-form]');
        const input = form?.querySelector('input[name="admin_password"]');
        const cancelButton = layer.querySelector('[data-pos-dialog-cancel]');

        const close = (value) => {
            document.removeEventListener('keydown', onKeydown);
            dismissPosDialog(layer, () => {
                previouslyFocused?.focus?.();
                resolve(value);
            });
        };

        const onKeydown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(null);
            }
        };

        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            close(input?.value || '');
        });
        cancelButton?.addEventListener('click', () => close(null), { once: true });
        document.addEventListener('keydown', onKeydown);
        input?.focus();
    });
}

function selectedVehicleId() {
    const value = (vehicleFilter?.value || '').trim().toLowerCase();

    if (!value || value === 'all vehicles') {
        return null;
    }

    return vehicleOptions.get(value)?.id || null;
}

function branchStockTooltip(product) {
    return product.branch_stock_tooltip || (product.branch_stock || [])
        .map((branch) => `${branch.branch}: ${branch.available}`)
        .join('\n');
}

function compatibleCarsTooltip(product) {
    return product.compatible_cars_tooltip || (product.compatible_cars || []).join('\n') || 'No vehicle fitment linked';
}

function compatibleCarsLabel(product) {
    const count = Number(product.compatible_cars_count ?? product.compatible_cars?.length ?? 0);

    return count > 0 ? `Fits ${count}` : 'No fitment';
}

function renderProductCards() {
    if (!productGrid) {
        return;
    }

    if (products.length === 0) {
        productGrid.innerHTML = '<div class="empty-state">No matching stocked parts are available at this branch.</div>';
        updateResultCount();
        return;
    }

    productGrid.innerHTML = products.map((product, index) => `
        <article class="part-card" data-product-index="${index}">
            <div class="part-card-top">
                <div class="part-card-heading">
                    <span class="product-type">${escapeHtml(product.product_type)}</span>
                    <span class="part-brand">${escapeHtml(product.brand)}</span>
                    <strong class="part-name">${escapeHtml(product.product_name)}</strong>
                    <span class="part-code">(${escapeHtml(product.product_code)})</span>
                </div>
                <button class="part-add-button" type="button" data-card-add="${index}" aria-label="Add ${escapeHtml(product.product_name)} to cart">+</button>
            </div>
            <div class="part-card-meta">
                <span class="branch-total-pill current">${escapeHtml(product.current_branch_name || currentBranch)} ${escapeHtml(product.current_branch_stock?.available ?? 0)}</span>
                <span class="branch-total-pill" title="${escapeHtml(branchStockTooltip(product))}">Other ${escapeHtml(product.other_available ?? 0)}</span>
                <span class="compatibility-pill" title="${escapeHtml(compatibleCarsTooltip(product))}">${escapeHtml(compatibleCarsLabel(product))}</span>
            </div>
            <span class="part-price">${escapeHtml(product.selling_price_display)}</span>
        </article>
    `).join('');

    productGrid.querySelectorAll('[data-card-add]').forEach((button) => {
        button.addEventListener('click', () => addProductToCart(products[Number(button.dataset.cardAdd)]));
    });

    updateResultCount();
}

function updateResultCount() {
    if (resultCount) {
        resultCount.textContent = `${products.length} ${products.length === 1 ? 'match' : 'matches'}`;
    }
}

async function loadProducts() {
    if (!endpoints.products) {
        return;
    }

    searchController?.abort();
    searchController = new AbortController();

    const params = new URLSearchParams();
    const search = (searchInput?.value || '').trim();
    const vehicleId = selectedVehicleId();
    const productType = (productTypeFilter?.value || '').trim();

    if (selectedSiteId) {
        params.set('site_id', selectedSiteId);
    }

    if (search) {
        params.set('search', search);
    }

    if (vehicleId) {
        params.set('compatible_car_model_id', vehicleId);
    } else {
        const vehicleText = (vehicleFilter?.value || '').trim();

        if (vehicleText && vehicleText.toLowerCase() !== 'all vehicles') {
            params.set('vehicle_search', vehicleText);
        }
    }

    if (productType && productType.toLowerCase() !== 'all product types') {
        params.set('product_type', productType);
    }

    productGrid?.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(`${endpoints.products}?${params}`, {
            headers: { Accept: 'application/json' },
            signal: searchController.signal,
        });
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message || 'Could not load POS products.');
        }

        products = payload.products || [];
        renderProductCards();
    } catch (error) {
        if (error.name !== 'AbortError') {
            await showAppAlert(error.message, { title: 'Could not load parts' });
        }
    } finally {
        productGrid?.removeAttribute('aria-busy');
    }
}

async function loadSuggestions() {
    const query = (searchInput?.value || '').trim();

    suggestionController?.abort();

    if (!endpoints.suggestions || query === '') {
        renderSuggestions([]);
        return;
    }

    suggestionController = new AbortController();

    try {
        const params = new URLSearchParams({ search: query });

        if (selectedSiteId) {
            params.set('site_id', selectedSiteId);
        }

        const response = await fetch(`${endpoints.suggestions}?${params}`, {
            headers: { Accept: 'application/json' },
            signal: suggestionController.signal,
        });
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message || 'Could not load suggestions.');
        }

        renderSuggestions(payload.suggestions || []);
    } catch (error) {
        if (error.name !== 'AbortError') {
            renderSuggestions([]);
        }
    }
}

function renderSuggestions(suggestions) {
    if (!suggestionsRow || !suggestionsList) {
        return;
    }

    if (suggestions.length === 0) {
        suggestionsRow.hidden = true;
        suggestionsList.innerHTML = '';
        return;
    }

    suggestionsRow.hidden = false;
    suggestionsList.innerHTML = suggestions.map((suggestion) => `
        <button type="button" data-suggestion-code="${escapeHtml(suggestion.product_code)}">
            ${escapeHtml(suggestion.label)}
        </button>
    `).join('');

    suggestionsList.querySelectorAll('button').forEach((button) => {
        button.addEventListener('click', () => {
            if (searchInput) {
                searchInput.value = button.dataset.suggestionCode || button.textContent.trim();
            }

            loadProducts();
            loadSuggestions();
            searchInput?.focus();
        });
    });
}

async function loadVehicleModels() {
    if (!endpoints.vehicleModels || !vehicleFilter) {
        return;
    }

    vehicleController?.abort();
    vehicleController = new AbortController();

    const params = new URLSearchParams({
        search: vehicleFilter.value.trim(),
    });

    try {
        const response = await fetch(`${endpoints.vehicleModels}?${params}`, {
            headers: { Accept: 'application/json' },
            signal: vehicleController.signal,
        });
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message || 'Could not load vehicle models.');
        }

        vehicleOptions = new Map();
        const datalist = document.querySelector('#pos-vehicle-options');

        if (datalist) {
            datalist.innerHTML = '<option value="All vehicles"></option>';
            (payload.vehicles || []).forEach((vehicle) => {
                const label = vehicle.display_name;
                vehicleOptions.set(label.toLowerCase(), vehicle);
                datalist.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(label)}"></option>`);
            });
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            vehicleOptions = new Map();
        }
    }
}

async function loadProductTypes() {
    if (!endpoints.productTypes || !productTypeFilter) {
        return;
    }

    productTypeController?.abort();
    productTypeController = new AbortController();

    const params = new URLSearchParams({
        search: productTypeFilter.value.trim(),
    });

    try {
        const response = await fetch(`${endpoints.productTypes}?${params}`, {
            headers: { Accept: 'application/json' },
            signal: productTypeController.signal,
        });
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message || 'Could not load product types.');
        }

        const datalist = document.querySelector('#pos-product-type-options');

        if (datalist) {
            datalist.innerHTML = '<option value="All product types"></option>';
            (payload.productTypes || []).forEach((productType) => {
                datalist.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(productType.name)}"></option>`);
            });
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            const datalist = document.querySelector('#pos-product-type-options');
            if (datalist) {
                datalist.innerHTML = '<option value="All product types"></option>';
            }
        }
    }
}

function addProductToCart(product) {
    if (!product?.product_id) {
        return;
    }

    const existing = cartItems.find((item) => item.product_id === product.product_id);
    const availableQuantity = Math.max(1, Number(product.current_branch_stock?.available) || 1);

    if (existing) {
        existing.available_quantity = availableQuantity;
        existing.quantity = Math.min(existing.quantity + 1, availableQuantity);
    } else {
        cartItems.push({
            product_id: product.product_id,
            product_code: product.product_code,
            product_name: product.product_name,
            quantity: 1,
            available_quantity: availableQuantity,
            unit_price: Number(product.selling_price) || 0,
            minimum_authorized_price: Number(product.minimum_authorized_price) || 0,
            maximum_discount_percentage: Number(product.maximum_discount_percentage) || 0,
        });
    }

    renderCart();
}

function changeCartQuantity(productId, delta) {
    const item = cartItems.find((cartItem) => cartItem.product_id === productId);

    if (!item) {
        return;
    }

    item.quantity = Math.min(
        item.quantity + delta,
        Math.max(1, Number(item.available_quantity) || 10000),
    );

    if (item.quantity <= 0) {
        cartItems = cartItems.filter((cartItem) => cartItem.product_id !== productId);
    }

    renderCart();
}

function setCartQuantity(productId, value) {
    const item = cartItems.find((cartItem) => cartItem.product_id === productId);
    const quantity = Number(value);

    if (!item || !Number.isInteger(quantity)) {
        renderCart();
        return;
    }

    item.quantity = Math.min(
        Math.max(1, quantity),
        Math.max(1, Number(item.available_quantity) || 10000),
    );

    renderCart();
}

function removeCartItem(productId) {
    cartItems = cartItems.filter((cartItem) => cartItem.product_id !== productId);
    renderCart();
}

function renderCart() {
    if (!cartList) {
        return;
    }

    if (cartItems.length === 0) {
        cartList.innerHTML = '<div class="cart-empty">Cart is empty.</div>';
    } else {
        cartList.innerHTML = cartItems.map((item) => {
            const maximumQuantity = Math.max(1, Number(item.available_quantity) || 10000);

            return `
            <article class="cart-line">
                <div class="cart-line-main">
                    <strong>${escapeHtml(item.product_code)}</strong>
                    <span>${escapeHtml(item.product_name)}</span>
                </div>
                <div class="cart-line-price">
                    <em>${formatCurrency(item.unit_price)}</em>
                    <span>x ${escapeHtml(item.quantity)}</span>
                </div>
                <div class="cart-line-actions" aria-label="Adjust ${escapeHtml(item.product_name)} quantity">
                    <button type="button" data-cart-minus="${item.product_id}" aria-label="Decrease quantity">-</button>
                    <label class="cart-quantity-field">
                        <span>Qty</span>
                        <input
                            type="number"
                            inputmode="numeric"
                            min="1"
                            max="${maximumQuantity}"
                            step="1"
                            value="${escapeHtml(item.quantity)}"
                            data-cart-quantity="${item.product_id}"
                            aria-label="Quantity for ${escapeHtml(item.product_name)}"
                        >
                    </label>
                    <button type="button" data-cart-plus="${item.product_id}" aria-label="Increase quantity" ${item.quantity >= maximumQuantity ? 'disabled' : ''}>+</button>
                    <button type="button" data-cart-remove="${item.product_id}" aria-label="Remove item">x</button>
                </div>
            </article>
        `;
        }).join('');

        cartList.querySelectorAll('[data-cart-minus]').forEach((button) => {
            button.addEventListener('click', () => changeCartQuantity(Number(button.dataset.cartMinus), -1));
        });
        cartList.querySelectorAll('[data-cart-plus]').forEach((button) => {
            button.addEventListener('click', () => changeCartQuantity(Number(button.dataset.cartPlus), 1));
        });
        cartList.querySelectorAll('[data-cart-quantity]').forEach((input) => {
            input.addEventListener('change', () => setCartQuantity(Number(input.dataset.cartQuantity), input.value));
        });
        cartList.querySelectorAll('[data-cart-remove]').forEach((button) => {
            button.addEventListener('click', () => removeCartItem(Number(button.dataset.cartRemove)));
        });
    }

    updateCartCount();
    updateCartPayload();
    updateTotals();
}

function updateCartCount() {
    const cartCount = document.querySelector('[data-cart-count]');

    if (cartCount) {
        cartCount.textContent = cartItems.reduce((sum, item) => sum + item.quantity, 0);
    }
}

function updateCartPayload() {
    if (cartPayload) {
        cartPayload.value = JSON.stringify(cartItems.map((item) => ({
            product_id: item.product_id,
            quantity: item.quantity,
        })));
        cartPayload.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function updateTotals() {
    const subtotal = cartItems.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
    const discountAmount = Math.max(0, parseCurrency(discountInput?.value || 0));
    const discountPercentage = subtotal > 0 ? (discountAmount / subtotal) * 100 : 0;
    const productLimit = cartItems.length > 0
        ? Math.min(...cartItems.map((item) => Math.max(0, Number(item.maximum_discount_percentage) || 0)))
        : configuredMaximumDiscountPercentage;
    const maximumDiscountPercentage = Math.min(configuredMaximumDiscountPercentage, productLimit);
    const maximumDiscountAmount = Math.round((subtotal * maximumDiscountPercentage / 100) * 100) / 100;
    const exceedsDiscountLimit = discountAmount > maximumDiscountAmount + 0.009;

    currentSaleTotal = Math.max(0, subtotal - discountAmount);

    if (subtotalLabel) {
        subtotalLabel.textContent = formatCurrency(subtotal);
    }

    if (discountInput) {
        discountInput.max = maximumDiscountAmount.toFixed(2);
        discountInput.setAttribute('aria-invalid', exceedsDiscountLimit ? 'true' : 'false');
    }

    if (discountPercentageLabel) {
        discountPercentageLabel.textContent = `${discountPercentage.toFixed(2)}%`;
    }

    if (discountLimitLabel) {
        discountLimitLabel.textContent = `${maximumDiscountPercentage.toFixed(2)}% (${formatCurrency(maximumDiscountAmount)})`;
    }

    if (discountFeedback) {
        discountFeedback.classList.toggle('is-invalid', exceedsDiscountLimit);
    }

    if (saleTotalLabel) {
        saleTotalLabel.textContent = formatCurrency(currentSaleTotal);
    }

    if (completeSaleButton) {
        completeSaleButton.disabled = exceedsDiscountLimit;
    }

    if (amountPaidInput && !amountPaidEdited) {
        amountPaidInput.value = Math.round(currentSaleTotal);
    }

    updatePaymentSummary();
}

function updatePaymentSummary() {
    const amountReceived = Math.max(0, parseCurrency(amountPaidInput?.value || 0));
    const amountStillOwed = Math.max(0, currentSaleTotal - amountReceived);

    if (totalDueLabel) {
        totalDueLabel.textContent = formatCurrency(amountStillOwed);
    }

    if (completeSaleTotalLabel) {
        completeSaleTotalLabel.textContent = `(${formatCurrency(amountReceived)})`;
        completeSaleTotalLabel.classList.remove('is-updating');
        void completeSaleTotalLabel.offsetWidth;
        completeSaleTotalLabel.classList.add('is-updating');
    }
}

function clearCart() {
    cartItems = [];
    amountPaidEdited = false;
    if (discountInput) {
        discountInput.value = '0';
    }
    renderCart();
}

async function changeSite(siteId, adminPassword = '') {
    const token = checkoutForm?.querySelector('input[name="_token"]')?.value;
    const formData = new FormData();

    formData.set('site_id', siteId);

    if (adminPassword) {
        formData.set('admin_password', adminPassword);
    }

    const response = await fetch(endpoints.site, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        body: formData,
    });
    const payload = await response.json();

    if (!response.ok) {
        const message = payload.errors?.admin_password?.[0] || payload.message || 'Could not change POS site.';
        throw new Error(message);
    }

    return payload.site;
}

const scheduleProducts = debounce(() => {
    loadProducts();
}, 250);
const scheduleSuggestions = debounce(() => {
    loadSuggestions();
}, 180);
const scheduleVehicleModels = debounce(() => {
    loadVehicleModels();
}, 180);
const scheduleProductTypes = debounce(() => {
    loadProductTypes();
}, 180);

searchInput?.addEventListener('input', () => {
    scheduleProducts();
    scheduleSuggestions();
});

vehicleFilter?.addEventListener('input', () => {
    scheduleVehicleModels();
    scheduleProducts();
});

filters.forEach((filter) => {
    if (filter === productTypeFilter) {
        filter.addEventListener('input', () => {
            scheduleProductTypes();
            scheduleProducts();
        });
        filter.addEventListener('change', scheduleProducts);
    } else if (filter !== vehicleFilter) {
        filter.addEventListener('input', scheduleProducts);
        filter.addEventListener('change', scheduleProducts);
    }
});

clearSearch?.addEventListener('click', (event) => {
    event.preventDefault();
    if (searchInput) {
        searchInput.value = '';
    }
    renderSuggestions([]);
    loadProducts();
    searchInput?.focus();
});

resetSearch?.addEventListener('click', () => {
    if (searchInput) {
        searchInput.value = '';
    }
    filters.forEach((filter) => {
        filter.value = '';
    });
    renderSuggestions([]);
    loadProducts();
    searchInput?.focus();
});

siteSelector?.addEventListener('change', async () => {
    const nextSiteId = siteSelector.value;
    const previousSiteId = selectedSiteId;
    let adminPassword = '';

    if (!endpoints.canChangeSiteDirectly) {
        const password = await requestAdminPassword();

        if (password === null) {
            siteSelector.value = previousSiteId;
            return;
        }

        adminPassword = password;
    }

    try {
        const site = await changeSite(nextSiteId, adminPassword);
        selectedSiteId = String(site.id);
        currentBranch = site.name;

        if (sourceSiteInput) {
            sourceSiteInput.value = site.id;
        }

        const branchHeading = document.querySelector('[data-pos-branch-name]');
        if (branchHeading) {
            branchHeading.textContent = site.name;
        }

        const headerSiteName = document.querySelector('[data-header-site-name]');
        if (headerSiteName) {
            headerSiteName.textContent = site.name;
        }

        clearCart();
        await loadProducts();
    } catch (error) {
        siteSelector.value = previousSiteId;
        await showAppAlert(error.message, { title: 'Could not change site' });
    }
});

document.querySelector('[data-pos-clear-cart]')?.addEventListener('click', (event) => {
    event.preventDefault();
    clearCart();
});

checkoutForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (cartItems.length === 0) {
        await showAppAlert('Add at least one product before completing the sale.', { title: 'Cart is empty' });
        return;
    }

    if (discountInput?.getAttribute('aria-invalid') === 'true') {
        await showAppAlert('Reduce the discount to the allowed percentage shown at checkout.', { title: 'Discount limit exceeded' });
        return;
    }

    try {
        const response = await fetch(checkoutForm.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(checkoutForm),
        });
        const payload = await response.json();

        if (!response.ok) {
            const errors = payload.errors || {};
            const firstError = Object.values(errors).flat()[0];
            throw new Error(firstError || payload.message || 'Could not complete sale.');
        }

        try {
            window.localStorage.setItem('partflow:sale-completed', String(Date.now()));
        } catch {
            // The dashboard polling fallback still refreshes sales when storage is unavailable.
        }
        await showAppAlert(payload.message || 'Sale completed successfully.', {
            title: 'Sale completed',
            tone: 'success',
        });
        clearCart();
        await loadProducts();
    } catch (error) {
        await showAppAlert(error.message, { title: 'Could not complete sale' });
    }
});

completeSaleButton?.addEventListener('click', () => {
    checkoutForm?.requestSubmit();
});

amountPaidInput?.addEventListener('input', () => {
    amountPaidEdited = true;
    updatePaymentSummary();
});

discountInput?.addEventListener('input', updateTotals);

renderProductCards();
renderCart();
loadVehicleModels();
loadProductTypes();
