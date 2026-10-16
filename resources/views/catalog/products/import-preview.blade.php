@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@push('scripts')
    @vite('resources/js/catalog.js')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.products.index') }}">Back to catalogue</a>
    <form class="import-header-form" method="POST" action="{{ route('web.catalog.products.import.cancel') }}">
        @csrf
        <button
            class="btn-secondary"
            type="submit"
            data-confirm-title="Cancel this import?"
            data-confirm="The review is discarded and nothing is saved. You will need to upload the file again."
            data-confirm-label="Cancel import"
        >Cancel</button>
    </form>
    <form id="import-post-form" class="import-header-form" method="POST" action="{{ route('web.catalog.products.import.store') }}" data-import-confirm-form>
        @csrf
        <input type="hidden" name="import_token" value="{{ $token }}">
        <button
            class="btn import-post-button"
            type="submit"
            data-confirm-title="Post this inventory?"
            data-confirm="This adds the stock to {{ $site }}, records a purchase of {{ $totalCost }}, and records a payment for the same amount.{{ $defaultLowStockRows > 0 ? ' '.number_format($defaultLowStockRows).' part(s) without a Warning Number will use the default of '.$defaultLowStockLevel.'.' : '' }}{{ $newTypes !== [] ? ' '.number_format(count($newTypes)).' new part type(s) will be created.' : '' }}"
            data-confirm-label="Post inventory"
        >Post inventory</button>
    </form>
@endsection

@section('content')
    <section class="catalogue-hub" data-import-review>
        <div class="catalogue-summary import-summary">
            @foreach ($summary as $item)
                <article>
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ $item['value'] }}</strong>
                    <p>{{ $item['detail'] }}</p>
                </article>
            @endforeach
        </div>
        <div class="catalogue-summary import-summary import-money">
            @foreach ($money as $item)
                <article @class([$item['tone']])>
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ $item['value'] }}</strong>
                    <p>{{ $item['detail'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    @if ($errors->hasAny(['merge', 'types', 'rows']))
        <div class="import-errors" role="alert">
            <strong>{{ match (true) { $errors->has('types') => 'Part types were not changed:', $errors->has('rows') => 'Rows were not removed:', default => 'Inventory was not posted:' } }}</strong>
            <ul>
                @foreach ([...$errors->get('merge'), ...$errors->get('types'), ...$errors->get('rows')] as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Shared by every row's Remove button (the button supplies the row number), so the page needs no form per row. --}}
    <form id="import-remove-row-form" method="POST" action="{{ route('web.catalog.products.import.rows.remove', $token) }}" hidden>
        @csrf
    </form>
    <form id="import-remove-duplicate-row-form" method="POST" action="{{ route('web.catalog.products.import.rows.remove', $token) }}" hidden>
        @csrf
        <input type="hidden" name="reopen" value="duplicates">
    </form>

    @if ($newTypes !== [])
        <form class="import-warning import-new-types" method="POST" action="{{ route('web.catalog.products.import.types', $token) }}">
            @csrf
            <strong>New part types</strong>
            <p>{{ number_format(count($newTypes)) }} part {{ \Illuminate\Support\Str::plural('type', count($newTypes)) }} in this sheet {{ count($newTypes) === 1 ? 'does' : 'do' }} not exist yet. Pick an existing type to use instead, or leave it blank to add it as a new type.</p>

            <datalist id="existing-part-types">
                @foreach ($existingTypeNames as $typeName)
                    <option value="{{ $typeName }}"></option>
                @endforeach
            </datalist>

            <div class="import-new-types-list">
                @foreach ($newTypes as $type)
                    <div class="import-new-type-row">
                        <div>
                            <strong>{{ $type['name'] }}</strong>
                            <small>{{ number_format($type['rows']) }} {{ \Illuminate\Support\Str::plural('row', $type['rows']) }}</small>
                        </div>
                        <div class="import-new-type-choice">
                            <input
                                class="form-control"
                                name="types[{{ $type['first_row'] }}]"
                                list="existing-part-types"
                                value="{{ old('types.'.$type['first_row']) }}"
                                placeholder="Leave blank to create &quot;{{ $type['name'] }}&quot;"
                                aria-label="Existing part type to use for {{ $type['name'] }}"
                                autocomplete="off"
                                data-import-type-input
                            >
                            @if ($type['suggestions'] !== [])
                                <span class="import-type-suggestions">
                                    <small>Closest:</small>
                                    @foreach ($type['suggestions'] as $suggestion)
                                        <button class="import-type-chip" type="button" data-import-type-suggestion="{{ $suggestion }}">{{ $suggestion }}</button>
                                    @endforeach
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="import-bulk-actions">
                <button
                    class="btn-secondary"
                    type="submit"
                    data-confirm-title="Apply part type choices?"
                    data-confirm="Rows with a chosen type will use that existing part type, and the review will be rebuilt. Types left blank will be created as new."
                    data-confirm-label="Apply part types"
                >Apply part type choices</button>
            </div>
        </form>
    @endif

    @if ($similarRows > 0)
        <div class="import-warning" role="alert">
            <strong>Similar parts found</strong>
            <p>{{ number_format($similarRows) }} {{ \Illuminate\Support\Str::plural('part', $similarRows) }} in this sheet look like parts already in the catalogue. For each one, marked "Similar" below, choose whether to add the stock to an existing part or create a new part.</p>
            <div class="import-bulk-actions">
                <button class="btn-secondary" type="button" data-import-merge-all="best">Add all to best match</button>
                <button class="btn-secondary" type="button" data-import-merge-all="new">Create all as new parts</button>
            </div>
        </div>
    @endif

    @if ($duplicateGroups !== [])
        @php($duplicateRowCount = array_sum(array_column($duplicateGroups, 'repeat_count')))
        <div class="import-warning" role="alert">
            <strong>Duplicate rows found</strong>
            <p>{{ number_format(count($duplicateGroups)) }} {{ \Illuminate\Support\Str::plural('part', count($duplicateGroups)) }} appear more than once ({{ number_format($duplicateRowCount) }} extra {{ \Illuminate\Support\Str::plural('row', $duplicateRowCount) }}). Unless you delete them, repeated rows are merged into the first row by adding their quantities.</p>
            <div class="import-bulk-actions">
                <button class="btn-secondary" type="button" data-open-import-duplicates>Review duplicates</button>
            </div>
        </div>

        <dialog class="import-duplicates-dialog" data-import-duplicates-dialog @if (! session('success') || session('duplicates_notice')) data-open-on-load @endif aria-labelledby="import-duplicates-title">
            <div class="import-duplicates-card">
                <header>
                    <div>
                        <span class="eyebrow">Before posting</span>
                        <h2 id="import-duplicates-title">Review duplicate rows</h2>
                    </div>
                    <button class="btn-secondary" type="button" data-close-import-duplicates>Close</button>
                </header>

                @if (session('duplicates_notice'))
                    <p class="import-duplicates-notice" role="status">{{ session('duplicates_notice') }} {{ number_format($duplicateRowCount) }} duplicate {{ \Illuminate\Support\Str::plural('row', $duplicateRowCount) }} left.</p>
                @endif

                <p class="form-help">
                    <strong>Merge by increment</strong> (the default) adds the quantities into one part. Different cost prices stay as separate purchase lines so the total is exact; the last row's selling prices and Warning Number are used.
                    <strong>Delete duplicates</strong> keeps only the first row of a part and drops the rest.
                </p>

                <div class="import-duplicates-list">
                    @foreach ($duplicateGroups as $group)
                        <article>
                            <div class="import-duplicates-head">
                                <div>
                                    <strong>{{ $group['name'] }}</strong>
                                    <small>{{ $group['type'] }}</small>
                                </div>
                                <span class="status-pill">{{ $group['repeat_count'] + 1 }} rows</span>
                            </div>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th class="numeric">Qty</th>
                                        <th class="numeric">Cost price</th>
                                        <th class="numeric">Selling (min - max)</th>
                                        <th><span class="sr-only">Remove</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['row_number'] }}{{ $loop->first ? ' (first)' : '' }}</td>
                                            <td class="numeric">{{ $row['quantity'] }}</td>
                                            <td class="numeric">{{ $row['unit_cost'] }}</td>
                                            <td class="numeric">{{ $row['selling'] }}</td>
                                            <td class="numeric">
                                                <button
                                                    class="import-row-remove"
                                                    type="submit"
                                                    form="import-remove-duplicate-row-form"
                                                    name="rows[]"
                                                    value="{{ $row['row_number'] }}"
                                                    data-import-remove-duplicate-row="{{ $row['row_number'] }}"
                                                    data-confirm-title="Remove row {{ $row['row_number'] }}?"
                                                    data-confirm="Row {{ $row['row_number'] }} ({{ $row['quantity'] }} x {{ $group['name'] }}) is dropped from this import. The other rows stay."
                                                    data-confirm-label="Remove row"
                                                >Remove</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($group['mixed_costs'] || $group['mixed_prices'])
                                <p class="import-duplicates-note">
                                    {{ $group['mixed_costs'] ? 'Cost prices differ between these rows.' : '' }}
                                    {{ $group['mixed_prices'] ? 'Selling prices differ; merging uses the last row\'s.' : '' }}
                                </p>
                            @endif
                            <div class="import-duplicates-actions">
                                <span class="form-help">Merged total: <strong>{{ $group['total_quantity'] }}</strong> units</span>
                                <form method="POST" action="{{ route('web.catalog.products.import.duplicates', $token) }}">
                                    @csrf
                                    <input type="hidden" name="rows[]" value="{{ $group['first_row'] }}">
                                    <button class="btn-secondary" type="submit">Delete all repeats (keep row {{ $group['first_row'] }})</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                <footer class="import-duplicates-footer">
                    <form method="POST" action="{{ route('web.catalog.products.import.duplicates', $token) }}">
                        @csrf
                        <button
                            class="btn-danger"
                            type="submit"
                            data-confirm-title="Delete all duplicate rows?"
                            data-confirm="Only the first row of each repeated part is kept; {{ number_format($duplicateRowCount) }} row(s) are dropped from this import."
                            data-confirm-label="Delete duplicates"
                        >Delete all duplicates</button>
                    </form>
                    <button class="btn" type="button" data-close-import-duplicates>Merge all by increment</button>
                </footer>
            </div>
        </dialog>
    @endif

    @if ($defaultLowStockRows > 0)
        <div class="import-warning" role="alert">
            <strong>Warning Number missing</strong>
            <p>{{ number_format($defaultLowStockRows) }} {{ \Illuminate\Support\Str::plural('part', $defaultLowStockRows) }} had no Warning Number, so the default low-stock warning level of {{ $defaultLowStockLevel }} will be used.</p>
            <p>These are marked "default" below. To use other levels, cancel, fill in the Warning Number column and upload again.</p>
        </div>
    @endif

    <section class="data-panel">
        <div class="panel-toolbar">
            <h2>What will be added, by part type</h2>
            <span class="form-help">Nothing is saved until you click Post inventory.</span>
        </div>
        <div class="table-wrap">
            <table class="data-table import-type-summary">
                <thead>
                    <tr>
                        <th>Part type</th>
                        <th class="numeric">Part lines</th>
                        <th class="numeric">New parts</th>
                        <th class="numeric">Restocked</th>
                        <th class="numeric">Units added</th>
                        <th class="numeric">Purchase price</th>
                        <th class="numeric">Min profit</th>
                        <th class="numeric">Max profit</th>
                        <th>Share of total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($typeSummary as $type)
                        <tr>
                            <td>
                                <strong>{{ $type['name'] }}</strong>
                                @if ($type['is_new_type'])
                                    <small class="import-new-type">New type</small>
                                @endif
                                @if ($type['similar_parts'])
                                    <small class="import-default-flag">{{ $type['similar_parts'] }} similar</small>
                                @endif
                            </td>
                            <td class="numeric">{{ $type['lines'] }}</td>
                            <td class="numeric">{{ $type['new_parts'] }}</td>
                            <td class="numeric">{{ $type['restocked_parts'] }}</td>
                            <td class="numeric">{{ $type['units'] }}</td>
                            <td class="numeric">{{ $type['total_cost'] }}</td>
                            <td class="numeric">{{ $type['min_profit'] }}</td>
                            <td class="numeric">{{ $type['max_profit'] }}</td>
                            <td>
                                <span class="import-share" title="{{ $type['share'] }}% of the purchase total">
                                    <span class="import-share-bar"><span style="width: {{ $type['share'] }}%"></span></span>
                                    <span class="import-share-label">{{ $type['share'] }}%</span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>{{ count($typeSummary) }} part type(s)</th>
                        <th class="numeric">{{ $typeTotals['lines'] }}</th>
                        <th class="numeric">{{ $typeTotals['new_parts'] }}</th>
                        <th class="numeric">{{ $typeTotals['restocked_parts'] }}</th>
                        <th class="numeric">{{ $typeTotals['units'] }}</th>
                        <th class="numeric">{{ $totalCost }}</th>
                        <th class="numeric">{{ $money['min_profit']['value'] }}</th>
                        <th class="numeric">{{ $money['max_profit']['value'] }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar import-preview-meta">
            <span><small>File</small> <strong>{{ $fileName }}</strong></span>
            <span><small>Site</small> <strong>{{ $site }}</strong></span>
            <span><small>Supplier</small> <strong>{{ $supplier }}</strong></span>
            <span><small>Paid from</small> <strong>{{ $paymentAccount }}</strong></span>
        </div>
        <div class="table-wrap">
            <table class="data-table import-preview-table">
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Item name (type)</th>
                        <th>Description (product)</th>
                        <th class="numeric">Qty</th>
                        <th class="numeric">Cost price</th>
                        <th class="numeric">Min selling</th>
                        <th class="numeric">Max selling</th>
                        <th class="numeric">Warning</th>
                        <th class="numeric">Line total</th>
                        <th>Action</th>
                        <th><span class="sr-only">Remove row</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @class(['import-similar-row' => $row['similar'] !== []])>
                            <td>{{ $row['row_number'] }}</td>
                            <td>
                                {{ $row['product_type'] }}
                                @if ($row['is_new_type'])
                                    <small class="import-new-type">New type</small>
                                @endif
                            </td>
                            <td>
                                {{ $row['product_name'] }}
                                @if ($row['price_changes'] !== [])
                                    <p class="form-help"><strong>Price changes detected</strong> — will be applied when posted.</p>
                                    @foreach ($row['price_changes'] as $change)
                                        <p class="form-help">{{ $change }}</p>
                                    @endforeach
                                @endif
                            </td>
                            <td class="numeric">{{ $row['quantity'] }}</td>
                            <td class="numeric">{{ $row['unit_cost'] }}</td>
                            <td class="numeric">{{ $row['minimum_selling_price'] }}</td>
                            <td class="numeric">{{ $row['maximum_selling_price'] }}</td>
                            <td class="numeric">
                                {{ $row['low_stock_level'] }}
                                @if ($row['low_stock_defaulted'])
                                    <small class="import-default-flag">default</small>
                                @endif
                            </td>
                            <td class="numeric">{{ $row['line_total'] }}</td>
                            <td>
                                @if ($row['similar'] !== [])
                                    @php($chosen = (string) old('merge.'.$row['row_number'], ''))
                                    <label class="import-merge-choice">
                                        <span class="status-pill warning">Similar</span>
                                        <select
                                            class="form-control"
                                            name="merge[{{ $row['row_number'] }}]"
                                            form="import-post-form"
                                            aria-label="Similar parts for row {{ $row['row_number'] }}"
                                            required
                                            data-import-merge-select
                                        >
                                            <option value="" @selected($chosen === '')>Choose: add to existing or create new</option>
                                            @foreach ($row['similar'] as $part)
                                                <option value="{{ $part['id'] }}" @selected($chosen === (string) $part['id'])>{{ $part['label'] }}</option>
                                            @endforeach
                                            <option value="new" @selected($chosen === 'new')>Create as a new part</option>
                                        </select>
                                    </label>
                                @else
                                    <span @class(['status-pill', $row['status_tone']])>{{ $row['status'] }}</span>
                                @endif
                            </td>
                            <td>
                                <button
                                    class="import-row-remove"
                                    type="submit"
                                    form="import-remove-row-form"
                                    name="rows[]"
                                    value="{{ $row['row_number'] }}"
                                    title="Remove row {{ $row['row_number'] }}"
                                    aria-label="Remove row {{ $row['row_number'] }}"
                                    data-import-remove-row="{{ $row['row_number'] }}"
                                    data-confirm-title="Remove row {{ $row['row_number'] }}?"
                                    data-confirm="Row {{ $row['row_number'] }} ({{ $row['quantity'] }} x {{ $row['product_name'] }}) is dropped from this import and the review is recalculated."
                                    data-confirm-label="Remove row"
                                >Remove</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="8">Purchase total (paid in full)</th>
                        <th class="numeric">{{ $totalCost }}</th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    @include('catalog.products.partials.import-progress', [
        'title' => 'Posting inventory',
        'steps' => ['Creating part types', 'Saving parts and applying detected price changes', 'Recording the purchase and stock at '.$site, 'Recording the payment'],
    ])
@endsection
