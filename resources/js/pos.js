// Local POS interactions only. API-backed search and sale creation can replace these fixtures later.
const products = JSON.parse(document.querySelector('#pos-products-data')?.textContent || '[]');
const currentBranch = JSON.parse(document.querySelector('#pos-current-branch')?.textContent || '""');

const searchInput = document.querySelector('#part-search');
const quickSearches = document.querySelectorAll('.quick-row button');
const clearSearch = document.querySelector('[data-pos-clear]');
const resetSearch = document.querySelector('[data-pos-reset]');
const resultCards = Array.from(document.querySelectorAll('.part-card'));
const resultCount = document.querySelector('[data-result-count]');
const filters = Array.from(document.querySelectorAll('[data-pos-filter]'));
const quantityInput = document.querySelector('[data-pos-quantity]');
const cartList = document.querySelector('[data-cart-list]');
const subtotalLabel = document.querySelector('[data-subtotal]');
const totalDueLabel = document.querySelector('[data-total-due]');
const cartPayload = document.querySelector('[data-cart-payload]');
const amountPaidInput = document.querySelector('[data-pos-amount-paid]');
const checkoutForm = document.querySelector('[data-pos-checkout-form]');
const completeSaleButton = document.querySelector('[data-complete-sale]');

let selectedProduct = products[0] || null;
let subtotal = parseCurrency(subtotalLabel?.textContent || '0');
let totalDue = parseCurrency(totalDueLabel?.textContent || '0');
let cartItems = [];

function formatCurrency(value) {
    return `MWK ${Math.round(value).toLocaleString('en-US')}`;
}

function parseCurrency(value) {
    return Number(String(value).replace(/[^\d.-]/g, '')) || 0;
}

function productSearchText(product) {
    return [
        product.product_code,
        product.product_name,
        product.pos_description,
        product.vehicle,
        product.part_type,
        product.brand,
        product.barcode,
        product.oem_number,
        product.part_country_of_origin,
        product.branch_stock_summary,
        ...(product.compatible_cars || []),
    ].join(' ').toLowerCase();
}

function activeFilterValues() {
    return filters
        .map((filter) => filter.value)
        .filter((value) => value && !value.startsWith('All'))
        .map((value) => value.toLowerCase());
}

function filterResults() {
    const query = (searchInput?.value || '').trim().toLowerCase();
    const selectedFilters = activeFilterValues();
    let visibleCount = 0;

    resultCards.forEach((card) => {
        const product = products[Number(card.dataset.productIndex)];
        const text = productSearchText(product);
        const matchesSearch = query === '' || text.includes(query);
        const matchesFilters = selectedFilters.every((filter) => text.includes(filter));
        const isVisible = matchesSearch && matchesFilters;

        card.hidden = !isVisible;
        visibleCount += isVisible ? 1 : 0;
    });

    if (resultCount) {
        resultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'match' : 'matches'}`;
    }
}

function setText(selector, value) {
    const element = document.querySelector(selector);

    if (element) {
        element.textContent = value;
    }
}

function renderBranchRows(product) {
    const container = document.querySelector('[data-branch-stock]');

    if (!container) {
        return;
    }

    container.innerHTML = product.branch_stock.map((branch) => {
        const classes = [
            branch.branch === currentBranch ? 'current' : '',
            branch.available === 0 ? 'empty' : '',
            branch.status === 'low' ? 'low' : '',
        ].filter(Boolean).join(' ');

        return `
            <div class="${classes}">
                <span>${branch.branch}</span>
                <strong>${branch.available}</strong>
            </div>
        `;
    }).join('');
}

function selectProduct(index) {
    selectedProduct = products[index];

    if (!selectedProduct) {
        return;
    }

    resultCards.forEach((card) => {
        card.classList.toggle('selected', Number(card.dataset.productIndex) === index);
    });

    const currentStock = selectedProduct.branch_stock.find((branch) => branch.branch === currentBranch);
    const available = currentStock?.available || 0;

    setText('[data-selected-name]', selectedProduct.product_name);
    setText('[data-selected-description]', selectedProduct.pos_description);
    setText('[data-selected-code]', selectedProduct.product_code);
    setText('[data-selected-vehicle]', selectedProduct.vehicle);
    setText('[data-selected-brand]', selectedProduct.brand);
    setText('[data-selected-oem]', selectedProduct.oem_number);
    setText('[data-current-available]', available);
    setText('[data-best-available]', selectedProduct.best_branch_available);
    setText('[data-best-branch]', selectedProduct.best_branch);
    setText('[data-total-available]', selectedProduct.total_available);
    setText('[data-selected-margin]', selectedProduct.margin_display);
    setText('[data-selected-price]', selectedProduct.selling_price_display);
    setText('[data-selected-compatible]', selectedProduct.compatible_cars.join(', '));
    setText(
        '[data-selected-reference]',
        `Barcode ${selectedProduct.barcode}. ${selectedProduct.tax_profile}. Origin ${selectedProduct.part_country_of_origin}.`
    );
    setText('[data-selected-status]', available > 0 ? 'Available' : 'Out of stock');
    setText('[data-selected-branch-summary]', selectedProduct.branch_stock_summary);

    const unitPriceInput = document.querySelector('[data-pos-unit-price]');

    if (unitPriceInput) {
        unitPriceInput.value = selectedProduct.selling_price_display;
    }

    renderBranchRows(selectedProduct);
}

function addSelectedProductToCart() {
    if (!selectedProduct?.product_id || !cartList) {
        return;
    }

    const quantity = Math.max(1, Number(quantityInput?.value || 1));
    const unitPrice = parseCurrency(document.querySelector('[data-pos-unit-price]')?.value || selectedProduct.selling_price);
    const lineTotal = unitPrice * quantity;
    const line = document.createElement('article');

    line.className = 'cart-line';
    line.innerHTML = `
        <div>
            <strong>${selectedProduct.product_name}</strong>
            <span>${selectedProduct.product_code} x ${quantity} at ${formatCurrency(unitPrice)}</span>
        </div>
        <em>${formatCurrency(lineTotal)}</em>
    `;

    cartList.appendChild(line);
    cartItems.push({
        product_id: selectedProduct.product_id,
        quantity,
        unit_price: unitPrice,
    });
    updateCartCount();
    updateCartPayload();
    subtotal += lineTotal;
    totalDue += lineTotal;

    if (subtotalLabel) {
        subtotalLabel.textContent = formatCurrency(subtotal);
    }

    if (totalDueLabel) {
        totalDueLabel.textContent = formatCurrency(totalDue);
    }

    if (amountPaidInput) {
        amountPaidInput.value = Math.round(totalDue);
    }
}

function updateCartCount() {
    const cartCount = document.querySelector('[data-cart-count]');

    if (cartCount && cartList) {
        cartCount.textContent = cartList.querySelectorAll('.cart-line').length;
    }
}

function updateCartPayload() {
    if (cartPayload) {
        cartPayload.value = JSON.stringify(cartItems);
    }
}

if (searchInput) {
    searchInput.addEventListener('input', filterResults);
}

filters.forEach((filter) => {
    filter.addEventListener('change', filterResults);
});

quickSearches.forEach((button) => {
    button.addEventListener('click', () => {
        searchInput.value = button.textContent.trim();
        filterResults();
        searchInput.focus();
    });
});

if (clearSearch) {
    clearSearch.addEventListener('click', (event) => {
        event.preventDefault();
        searchInput.value = '';
        filterResults();
        searchInput.focus();
    });
}

if (resetSearch) {
    resetSearch.addEventListener('click', () => {
        searchInput.value = '';
        filters.forEach((filter) => {
            filter.selectedIndex = 0;
        });
        filterResults();
        searchInput.focus();
    });
}

resultCards.forEach((card) => {
    card.addEventListener('click', () => {
        selectProduct(Number(card.dataset.productIndex));
    });
});

document.querySelector('[data-add-to-cart]')?.addEventListener('click', addSelectedProductToCart);
document.querySelector('[data-pos-clear-cart]')?.addEventListener('click', (event) => {
    event.preventDefault();

    if (!cartList) {
        return;
    }

    cartList.innerHTML = '';
    cartItems = [];
    updateCartPayload();
    subtotal = 0;
    totalDue = 0;

    if (subtotalLabel) {
        subtotalLabel.textContent = formatCurrency(subtotal);
    }

    if (totalDueLabel) {
        totalDueLabel.textContent = formatCurrency(totalDue);
    }

    updateCartCount();
});

completeSaleButton?.addEventListener('click', () => {
    checkoutForm?.requestSubmit();
});

filterResults();
updateCartCount();
updateCartPayload();
