// POS search mock interactions: keep the Blade page usable before live API wiring.
const searchInput = document.querySelector('#part-search');
const quickSearches = document.querySelectorAll('.quick-row button');
const clearSearch = document.querySelector('.quick-row a');
const resultCards = document.querySelectorAll('.result-card');

function filterResults(value) {
    const query = value.trim().toLowerCase();

    // Hide non-matching cards without destroying the selected state.
    resultCards.forEach((card) => {
        const text = card.textContent.toLowerCase();
        card.hidden = query !== '' && !text.includes(query);
    });
}

if (searchInput) {
    searchInput.addEventListener('input', (event) => {
        filterResults(event.target.value);
    });
}

quickSearches.forEach((button) => {
    button.addEventListener('click', () => {
        searchInput.value = button.textContent.trim();
        filterResults(searchInput.value);
        searchInput.focus();
    });
});

if (clearSearch) {
    clearSearch.addEventListener('click', (event) => {
        event.preventDefault();
        searchInput.value = '';
        filterResults('');
        searchInput.focus();
    });
}

resultCards.forEach((card) => {
    card.addEventListener('click', () => {
        // Mirror the selected product state a cashier expects while scanning search results.
        resultCards.forEach((item) => item.classList.remove('selected'));
        card.classList.add('selected');
    });
});
