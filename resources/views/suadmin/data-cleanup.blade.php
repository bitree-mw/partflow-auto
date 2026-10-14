@extends('layouts.suadmin')

@php
    $chosen = old('categories', $selection['categories'] ?? []);
    $from = old('from', $selection['from'] ?? '');
    $to = old('to', $selection['to'] ?? '');
    $groups = collect($categories)->groupBy('group', preserveKeys: true);
    $completed = session('cleanupSummary');
    $stockNotes = [
        'zeroed' => 'Every stock quantity and reservation was set to zero (full wipe of all stock transactions).',
        'reversed' => 'The stock effect of the deleted transactions was undone at :rows product/site stock line(s).',
        'unchanged' => 'Stock quantities were not changed.',
    ];
@endphp

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <h2>Permanently delete system data</h2>
            <span class="suadmin-muted">Use this to start fresh. Deleted records cannot be recovered, so take a database backup first.</span>
        </div>

        <div class="suadmin-panel-body">
            <p class="suadmin-muted" style="margin: 0;">
                <strong>Never deleted:</strong> vehicle makes and models, brands, product (part) types, sites, roles,
                tax profiles, fuel types and business settings.
            </p>

            <form class="suadmin-cleanup-form" method="POST" action="{{ route('suadmin.data-cleanup.preview') }}">
                @csrf

                <label class="checkbox-field">
                    <input type="checkbox" data-cleanup-select-all @checked(count($chosen) === count($categories))>
                    Select everything
                </label>

                <div class="suadmin-cleanup-groups">
                    @foreach ($groups as $group => $items)
                        <fieldset class="suadmin-cleanup-group">
                            <legend>{{ $group }}</legend>
                            @foreach ($items as $key => $category)
                                <label class="suadmin-cleanup-option">
                                    <input type="checkbox" name="categories[]" value="{{ $key }}" data-cleanup-category @checked(in_array($key, $chosen, true))>
                                    <span>
                                        <strong>{{ $category['label'] }}</strong>
                                        @if ($category['hint'])
                                            <small class="suadmin-muted">{{ $category['hint'] }}</small>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
                <x-form-error name="categories" />

                <div class="form-grid">
                    <div class="form-field">
                        <label for="from">From date (optional)</label>
                        <input class="form-control" id="from" name="from" type="date" value="{{ $from }}">
                        <x-form-error name="from" />
                    </div>
                    <div class="form-field">
                        <label for="to">To date (optional)</label>
                        <input class="form-control" id="to" name="to" type="date" value="{{ $to }}">
                        <x-form-error name="to" />
                    </div>
                </div>
                <p class="suadmin-muted" style="margin: 0;">
                    Leave both dates empty to delete all records of the ticked types. Transactions are matched on their
                    document or expense date; everything else on the date it was created. Deleting all sales, purchases,
                    transfers and adjustments with no dates sets every stock quantity to zero.
                </p>

                <div class="form-actions">
                    <button class="btn" type="submit">Preview what will be deleted</button>
                </div>
            </form>
        </div>
    </section>

    @if ($preview)
        <section class="data-panel">
            <div class="panel-toolbar">
                <h2>Preview</h2>
                <span class="suadmin-muted">
                    @if ($selection['from'] || $selection['to'])
                        Records dated {{ $selection['from'] ?: 'any time' }} to {{ $selection['to'] ?: 'today' }}.
                    @else
                        All dates.
                    @endif
                    Nothing has been deleted yet.
                </span>
            </div>

            @include('suadmin.partials.cleanup-summary', ['summary' => $preview, 'stockNotes' => [
                'zeroed' => 'Every stock quantity and reservation will be set to zero.',
                'reversed' => 'The stock effect of the deleted transactions will be undone at :rows product/site stock line(s). Quantities never go below zero.',
                'unchanged' => 'Stock quantities will not change.',
            ]])

            <form class="suadmin-panel-body" method="POST" action="{{ route('suadmin.data-cleanup.destroy') }}">
                @csrf
                @method('DELETE')
                @foreach ($selection['categories'] as $key)
                    <input type="hidden" name="categories[]" value="{{ $key }}">
                @endforeach
                <input type="hidden" name="from" value="{{ $selection['from'] }}">
                <input type="hidden" name="to" value="{{ $selection['to'] }}">

                <div class="form-grid">
                    <div class="form-field">
                        <label for="password">Super admin password</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                        <x-form-error name="password" />
                    </div>
                    <div class="form-field">
                        <label for="confirmation">Type DELETE to confirm</label>
                        <input class="form-control" id="confirmation" name="confirmation" autocomplete="off" required>
                        <x-form-error name="confirmation" />
                    </div>
                </div>

                <div class="form-actions">
                    <button
                        class="btn btn-danger"
                        type="submit"
                        data-confirm-title="Delete this data permanently?"
                        data-confirm="The records listed in the preview will be deleted for good. This cannot be undone."
                        data-confirm-label="Delete permanently"
                    >Delete permanently</button>
                </div>
            </form>
        </section>
    @endif

    @if ($completed)
        <section class="data-panel">
            <div class="panel-toolbar">
                <h2>Last cleanup result</h2>
            </div>
            @include('suadmin.partials.cleanup-summary', ['summary' => $completed, 'stockNotes' => $stockNotes])
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const selectAll = document.querySelector('[data-cleanup-select-all]');
            const boxes = [...document.querySelectorAll('[data-cleanup-category]')];

            if (! selectAll) {
                return;
            }

            selectAll.addEventListener('change', () => boxes.forEach((box) => { box.checked = selectAll.checked; }));
            boxes.forEach((box) => box.addEventListener('change', () => {
                selectAll.checked = boxes.every((item) => item.checked);
            }));
        })();
    </script>
@endpush
