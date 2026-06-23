<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PartFlow Auto POS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pos-shell">
    <main class="pos-workspace" aria-label="PartFlow Auto point of sale">
        <section class="pos-topbar" aria-label="POS status">
            <div>
                <span class="pos-kicker">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 7.5 12.5 15 9 11.5 4 16.5"></path>
                        <path d="M15 7.5h5v5"></path>
                    </svg>
                    POS cross-branch
                </span>
                <h1>PartFlow Auto</h1>
            </div>
            <div class="pos-till">
                <span>Area 23 Till</span>
                <strong>Online</strong>
            </div>
        </section>

        <section class="pos-grid">
            <aside class="pos-panel pos-search-panel" aria-label="Product search">
                <header class="panel-header">
                    <div>
                        <span class="panel-label">Search shelf</span>
                        <strong>4 branches indexed</strong>
                    </div>
                    <button class="icon-button" type="button" aria-label="Reset search">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 12a9 9 0 1 0 3-6.7"></path>
                            <path d="M3 4v6h6"></path>
                        </svg>
                    </button>
                </header>

                <label class="search-field" for="part-search">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.5-3.5"></path>
                    </svg>
                    <input id="part-search" type="search" value="amox" autocomplete="off" aria-label="Search products">
                    <span>41ms</span>
                </label>

                <div class="quick-row" aria-label="Quick search chips">
                    <span>Try</span>
                    <button type="button">amox</button>
                    <button type="button">parac</button>
                    <button type="button">ibu</button>
                    <button type="button">ceti</button>
                    <a href="#" aria-label="Clear search">Clear</a>
                </div>

                <div class="result-list" aria-label="Matching products">
                    <article class="result-card selected">
                        <div>
                            <h2>Amoxicillin <span>500mg cap</span></h2>
                            <p>Pack x21 · 60 across network</p>
                        </div>
                        <div class="branch-pills">
                            <span class="home-branch">Area 23 12</span>
                            <span>Old Town 40</span>
                            <span>City Centre 8</span>
                            <span class="muted">Mzuzu 0</span>
                        </div>
                    </article>

                    <article class="result-card">
                        <div>
                            <h2>Amoxiclav <span>625mg tab</span></h2>
                            <p>Pack x14 · 27 across network</p>
                        </div>
                        <div class="branch-pills">
                            <span class="home-branch">Area 23 3</span>
                            <span>Old Town 18</span>
                            <span class="muted">City Centre 0</span>
                            <span>Mzuzu 6</span>
                        </div>
                    </article>

                    <article class="result-card">
                        <div>
                            <h2>Paracetamol <span>500mg tab</span></h2>
                            <p>Pack x24 · 321 across network</p>
                        </div>
                        <div class="branch-pills">
                            <span class="home-branch">Area 23 89</span>
                            <span>Old Town 120</span>
                            <span>City Centre 67</span>
                            <span>Mzuzu 45</span>
                        </div>
                    </article>

                    <article class="result-card">
                        <div>
                            <h2>Ibuprofen <span>400mg tab</span></h2>
                            <p>Pack x16 · 78 across network</p>
                        </div>
                        <div class="branch-pills">
                            <span class="home-branch">Area 23 24</span>
                            <span>Old Town 38</span>
                            <span>City Centre 12</span>
                            <span>Mzuzu 4</span>
                        </div>
                    </article>
                </div>

                <footer class="panel-footer">
                    <span>8 matches</span>
                    <span>Indexed at the till</span>
                </footer>
            </aside>

            <section class="pos-panel pos-detail-panel" aria-label="Selected item details">
                <div class="part-header">
                    <div>
                        <span class="panel-label">Selected part</span>
                        <h2>Amoxicillin 500mg cap</h2>
                        <p>AMOX-500-CAP · Barcode 60012900421</p>
                    </div>
                    <span class="stock-state">Available</span>
                </div>

                <div class="availability-strip" aria-label="Branch stock summary">
                    <div class="branch-tile primary">
                        <span>Current branch</span>
                        <strong>12</strong>
                        <small>Area 23</small>
                    </div>
                    <div class="branch-tile">
                        <span>Best branch</span>
                        <strong>40</strong>
                        <small>Old Town</small>
                    </div>
                    <div class="branch-tile warning">
                        <span>Reserved</span>
                        <strong>3</strong>
                        <small>Network</small>
                    </div>
                    <div class="branch-tile">
                        <span>Total</span>
                        <strong>60</strong>
                        <small>All branches</small>
                    </div>
                </div>

                <div class="cross-branch-card">
                    <header>
                        <span class="panel-label">Cross-branch availability</span>
                        <button type="button">Reserve transfer</button>
                    </header>
                    <div class="branch-table" role="table" aria-label="Branch availability">
                        <div class="branch-row head" role="row">
                            <span>Branch</span>
                            <span>On hand</span>
                            <span>Reserved</span>
                            <span>Available</span>
                        </div>
                        <div class="branch-row current" role="row">
                            <span>Area 23</span>
                            <span>15</span>
                            <span>3</span>
                            <strong>12</strong>
                        </div>
                        <div class="branch-row" role="row">
                            <span>Old Town</span>
                            <span>42</span>
                            <span>2</span>
                            <strong>40</strong>
                        </div>
                        <div class="branch-row" role="row">
                            <span>City Centre</span>
                            <span>8</span>
                            <span>0</span>
                            <strong>8</strong>
                        </div>
                        <div class="branch-row depleted" role="row">
                            <span>Mzuzu</span>
                            <span>0</span>
                            <span>0</span>
                            <strong>0</strong>
                        </div>
                    </div>
                </div>

                <div class="sale-card">
                    <header>
                        <span class="panel-label">Current sale</span>
                        <strong>Invoice #POS-1042</strong>
                    </header>
                    <div class="sale-line">
                        <span>Amoxicillin 500mg cap</span>
                        <em>2 x MWK 8,500</em>
                    </div>
                    <div class="sale-line muted">
                        <span>VAT inclusive</span>
                        <em>MWK 2,532</em>
                    </div>
                    <div class="sale-total">
                        <span>Total</span>
                        <strong>MWK 17,000</strong>
                    </div>
                    <div class="action-row">
                        <button class="ghost-button" type="button">Hold</button>
                        <button class="pay-button" type="button">Pay now</button>
                    </div>
                </div>
            </section>
        </section>
    </main>
</body>
</html>
