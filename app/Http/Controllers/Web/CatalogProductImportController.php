<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ConfirmCatalogProductImportRequest;
use App\Http\Requests\Web\MapImportTypesRequest;
use App\Http\Requests\Web\PreviewCatalogProductImportRequest;
use App\Http\Requests\Web\RemoveImportDuplicatesRequest;
use App\Http\Requests\Web\RemoveImportRowsRequest;
use App\Models\Contact;
use App\Models\PaymentAccount;
use App\Models\Site;
use App\Services\CatalogProductImportService;
use App\Services\ContactService;
use App\Services\PaymentAccountService;
use App\Services\SiteAccessService;
use App\Services\SiteService;
use App\Services\SystemConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogProductImportController extends Controller
{
    // One pending import per session: a new upload replaces any preview not yet confirmed.
    private const SESSION_KEY = 'catalog_product_import';

    public function __construct(
        private readonly CatalogProductImportService $importService,
        private readonly SiteService $siteService,
        private readonly ContactService $contactService,
        private readonly PaymentAccountService $paymentAccountService,
        private readonly SystemConfigurationService $systemConfiguration
    ) {}

    public function create(Request $request): View
    {
        return view('catalog.products.import', [
            'title' => 'Import Parts',
            'description' => 'Upload a CSV or Excel sheet to create parts, receive their stock, and record the purchase and payment in one step.',
            'sites' => $this->siteService
                ->list(['is_active' => true], $request->user(), SiteAccessService::RECEIVE_STOCK)
                ->map(fn (Site $site): array => ['id' => $site->id, 'label' => "{$site->name} ({$site->code})"])
                ->all(),
            'suppliers' => $this->contactService->list()
                ->filter->isSupplier()
                ->map(fn (Contact $contact): array => ['id' => $contact->id, 'label' => "{$contact->name} ({$contact->code})"])
                ->values()
                ->all(),
            'paymentAccounts' => $this->paymentAccountService->list(['is_active' => true])
                ->map(fn (PaymentAccount $account): array => [
                    'id' => $account->id,
                    'label' => "{$account->account_name} (".str($account->account_type)->headline().')',
                ])
                ->all(),
            'defaultPaymentAccountId' => $this->systemConfiguration->defaultPaymentAccountId(),
            'templateHeaders' => CatalogProductImportService::TEMPLATE_HEADERS,
            'maxRows' => CatalogProductImportService::MAX_ROWS,
            'maxBytes' => CatalogProductImportService::MAX_FILE_KB * 1024,
            'defaultLowStockLevel' => CatalogProductImportService::DEFAULT_LOW_STOCK_LEVEL,
        ]);
    }

    public function preview(PreviewCatalogProductImportRequest $request): RedirectResponse|JsonResponse
    {
        $file = $request->file('file');
        $context = $request->importContext();
        $preview = $this->importService->preview(
            $file->getRealPath(),
            $file->getClientOriginalExtension(),
            $context,
            $request->user()
        );
        $token = (string) Str::uuid();

        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'file_name' => Str::limit($file->getClientOriginalName(), 120),
            'context' => $context,
            'rows' => $preview['rows'],
            'totals' => $preview['totals'],
        ]);

        // The upload page posts in the background to show progress, then follows this URL itself.
        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('web.catalog.products.import.show', $token)]);
        }

        return redirect()->route('web.catalog.products.import.show', $token);
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $pending = $this->pendingImport($request, $token);

        if (! $pending) {
            return redirect()
                ->route('web.catalog.products.import.create')
                ->with('error', 'That import preview has expired or was already imported. Upload the file again.');
        }

        $currency = config('services.partflow.base_currency', 'MWK');
        $totals = $pending['totals'];
        $money = $this->importService->financials($pending['rows']);
        $similarRows = collect($pending['rows'])->filter(fn (array $row): bool => ($row['similar'] ?? []) !== [])->count();

        return view('catalog.products.import-preview', [
            'title' => 'Review Parts Import',
            'description' => 'Check the parts, quantities, and costs before the stock, purchase, and payment are recorded.',
            'token' => $token,
            'fileName' => $pending['file_name'],
            'site' => Site::query()->find($pending['context']['site_id'])?->name ?? 'Unknown site',
            'supplier' => filled($pending['context']['contact_id'])
                ? (Contact::query()->find($pending['context']['contact_id'])?->name ?? 'Unknown supplier')
                : 'No supplier',
            'paymentAccount' => PaymentAccount::query()->find($pending['context']['payment_account_id'])?->account_name ?? 'Unknown account',
            'summary' => [
                ['label' => 'Part lines', 'value' => number_format($totals['lines']), 'detail' => number_format($totals['units']).' units in total'
                    .(($totals['combined_rows'] ?? 0) > 0 ? ', '.number_format($totals['combined_rows']).' duplicate row(s) merged' : '')],
                ['label' => 'New parts', 'value' => number_format($totals['new_parts']), 'detail' => number_format($totals['new_types']).' new product types'],
                ['label' => 'Restocked parts', 'value' => number_format($totals['restocked_parts']), 'detail' => 'Exact matches; prices updated from the sheet'],
                ['label' => 'Similar parts found', 'value' => number_format($similarRows), 'detail' => $similarRows ? 'Choose add or create for each below' : 'No near-duplicates in the catalogue'],
            ],
            'money' => [
                'purchase' => ['label' => 'Total purchase price', 'value' => $currency.' '.$this->money($money['purchase_total']), 'detail' => 'Paid in full from the chosen account', 'tone' => ''],
                'stock_value' => ['label' => 'Stock value at min selling price', 'value' => $currency.' '.$this->money($money['min_value']), 'detail' => 'At max selling price: '.$currency.' '.$this->money($money['max_value']), 'tone' => ''],
                'min_profit' => ['label' => 'Minimum expected profit', 'value' => $currency.' '.$this->money($money['min_profit']), 'detail' => $this->percent($money['min_profit_percent']).' of purchase price, selling at min prices', 'tone' => $money['min_profit'] < 0 ? 'negative' : 'positive'],
                'max_profit' => ['label' => 'Maximum expected profit', 'value' => $currency.' '.$this->money($money['max_profit']), 'detail' => $this->percent($money['max_profit_percent']).' of purchase price, selling at max prices', 'tone' => $money['max_profit'] < 0 ? 'negative' : 'positive'],
            ],
            'similarRows' => $similarRows,
            'newTypes' => $this->importService->newTypeGroups($pending['rows']),
            'existingTypeNames' => $this->importService->partTypeNames(),
            'duplicateGroups' => collect($this->importService->duplicateGroups($pending['rows']))->map(fn (array $group): array => [
                ...$group,
                'repeat_count' => count($group['rows']) - 1,
                'total_quantity' => number_format($group['total_quantity']),
                'rows' => collect($group['rows'])->map(fn (array $row): array => [
                    'row_number' => $row['row_number'],
                    'quantity' => number_format($row['quantity']),
                    'unit_cost' => $this->money($row['unit_cost']),
                    'selling' => $this->money($row['minimum_selling_price']).' - '.$this->money($row['maximum_selling_price']),
                ])->all(),
            ])->all(),
            'typeSummary' => collect($this->importService->typeSummary($pending['rows']))->map(fn (array $type): array => [
                'name' => $type['name'],
                'is_new_type' => $type['is_new_type'],
                'lines' => number_format($type['lines']),
                'new_parts' => number_format($type['new_parts']),
                'restocked_parts' => number_format($type['restocked_parts']),
                'units' => number_format($type['units']),
                'total_cost' => $this->money($type['total_cost']),
                'min_profit' => $this->money($type['min_profit']),
                'max_profit' => $this->money($type['max_profit']),
                'similar_parts' => $type['similar_parts'],
                'share' => $totals['total_cost'] > 0 ? round($type['total_cost'] / $totals['total_cost'] * 100) : 0,
            ])->all(),
            'defaultLowStockRows' => (int) ($totals['default_low_stock'] ?? 0),
            'defaultLowStockLevel' => CatalogProductImportService::DEFAULT_LOW_STOCK_LEVEL,
            'typeTotals' => [
                'lines' => number_format($totals['lines']),
                'new_parts' => number_format($totals['new_parts']),
                'restocked_parts' => number_format($totals['restocked_parts']),
                'units' => number_format($totals['units']),
            ],
            'rows' => collect($pending['rows'])->map(fn (array $row): array => [
                'row_number' => $row['row_number'],
                'product_type' => $row['product_type_name'],
                'product_name' => $row['product_name'],
                'quantity' => number_format($row['quantity']),
                'unit_cost' => $this->money($row['unit_cost']),
                'minimum_selling_price' => $this->money($row['minimum_selling_price']),
                'maximum_selling_price' => $this->money($row['maximum_selling_price']),
                'low_stock_level' => number_format($row['low_stock_level']),
                'low_stock_defaulted' => (bool) ($row['low_stock_defaulted'] ?? false),
                'line_total' => $this->money($row['line_total']),
                'is_new_type' => $row['is_new_type'],
                'status' => match (true) {
                    ($row['combined_into'] ?? null) !== null => 'Merged into row '.$row['combined_into'],
                    (bool) $row['existing_product_id'] => 'Restock '.$row['existing_product_code'],
                    default => 'New part',
                },
                'status_tone' => match (true) {
                    ($row['combined_into'] ?? null) !== null => 'inactive',
                    (bool) $row['existing_product_id'] => 'warning',
                    default => 'success',
                },
                'similar' => collect($row['similar'] ?? [])->map(fn (array $part): array => [
                    'id' => $part['id'],
                    'label' => "Add to {$part['code']} - {$part['name']} ({$part['type']}, {$part['score']}% match)",
                ])->all(),
            ])->all(),
            'totalCost' => $currency.' '.$this->money($totals['total_cost']),
        ]);
    }

    public function store(ConfirmCatalogProductImportRequest $request): RedirectResponse|JsonResponse
    {
        $token = $request->validated('import_token');
        $pending = $this->pendingImport($request, $token);
        $expired = 'That import preview has expired or was already imported. Upload the file again.';

        if (! $pending) {
            if ($request->expectsJson()) {
                return $this->jsonFailure($expired, [], route('web.catalog.products.import.create'));
            }

            return redirect()
                ->route('web.catalog.products.import.create')
                ->with('error', $expired);
        }

        // Checked while the preview is still held, so a missing choice returns to the same review page.
        try {
            $merges = $this->importService->resolveDecisions($pending['rows'], $request->decisions());
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Choose what to do with each similar part.', 'errors' => $exception->errors()], 422);
            }

            return redirect()->route('web.catalog.products.import.show', $token)->withErrors($exception->errors())->withInput();
        }

        // Clear the preview before writing so a double submit cannot import the same sheet twice.
        $request->session()->forget(self::SESSION_KEY);

        try {
            $purchase = $this->importService->import(
                $pending['rows'],
                $pending['context'],
                $request->user(),
                $pending['file_name'],
                $merges
            );
        } catch (ValidationException $exception) {
            // Nothing was saved (the import runs in one transaction); the preview is gone, so start again.
            $notSaved = 'The import was not saved. Fix the problems below and upload the file again.';

            if ($request->expectsJson()) {
                return $this->jsonFailure($notSaved, $exception->errors(), route('web.catalog.products.import.create'));
            }

            return redirect()
                ->route('web.catalog.products.import.create')
                ->withErrors($exception->errors())
                ->with('error', $notSaved);
        }

        $success = sprintf(
            'Posted %s part lines to inventory. Purchase %s recorded and paid: %s %s.',
            number_format($pending['totals']['lines']),
            $purchase->document_number,
            config('services.partflow.base_currency', 'MWK'),
            $this->money((float) $purchase->total_amount)
        );

        if ($request->expectsJson()) {
            // Flashed now so the catalogue shows it when the page follows the redirect.
            $request->session()->flash('success', $success);

            return response()->json(['redirect' => route('web.catalog.products.index')]);
        }

        return redirect()->route('web.catalog.products.index')->with('success', $success);
    }

    public function removeDuplicates(RemoveImportDuplicatesRequest $request, string $token): RedirectResponse
    {
        $pending = $this->pendingImport($request, $token);

        if (! $pending) {
            return redirect()
                ->route('web.catalog.products.import.create')
                ->with('error', 'That import preview has expired or was already imported. Upload the file again.');
        }

        $result = $this->importService->removeDuplicateRows($pending['rows'], $request->firstRows());

        $request->session()->put(self::SESSION_KEY, [...$pending, 'rows' => $result['rows'], 'totals' => $result['totals']]);

        return $this->backToReview($token, $result['rows'], reopenDuplicates: true, message: $result['removed'] > 0
            ? 'Removed '.number_format($result['removed']).' duplicate row(s). Each part keeps its first row only.'
            : 'There were no duplicate rows to remove.');
    }

    public function removeRows(RemoveImportRowsRequest $request, string $token): RedirectResponse
    {
        $pending = $this->pendingImport($request, $token);

        if (! $pending) {
            return redirect()
                ->route('web.catalog.products.import.create')
                ->with('error', 'That import preview has expired or was already imported. Upload the file again.');
        }

        try {
            $result = $this->importService->removeRows($pending['rows'], $request->rowNumbers());
        } catch (ValidationException $exception) {
            return redirect()->route('web.catalog.products.import.show', $token)->withErrors($exception->errors());
        }

        $request->session()->put(self::SESSION_KEY, [...$pending, 'rows' => $result['rows'], 'totals' => $result['totals']]);
        $rows = $request->rowNumbers();

        return $this->backToReview($token, $result['rows'], $request->reopensDuplicates(), $result['removed'] > 0
            ? (count($rows) === 1 ? "Removed row {$rows[0]} from this import." : 'Removed '.number_format($result['removed']).' rows from this import.')
            : 'Those rows were already removed.');
    }

    /**
     * Returns to the review. After a change made in the duplicates dialog, the dialog reopens with the message
     * while duplicates remain, so the user can carry on; otherwise the usual success message is shown.
     */
    private function backToReview(string $token, array $rows, bool $reopenDuplicates, string $message): RedirectResponse
    {
        $redirect = redirect()->route('web.catalog.products.import.show', $token);

        if ($reopenDuplicates && $this->importService->duplicateGroups($rows) !== []) {
            return $redirect->with('duplicates_notice', $message);
        }

        return $redirect->with('success', $message);
    }

    public function mapTypes(MapImportTypesRequest $request, string $token): RedirectResponse
    {
        $pending = $this->pendingImport($request, $token);

        if (! $pending) {
            return redirect()
                ->route('web.catalog.products.import.create')
                ->with('error', 'That import preview has expired or was already imported. Upload the file again.');
        }

        try {
            $result = $this->importService->remapTypes($pending['rows'], $request->choices());
        } catch (ValidationException $exception) {
            return redirect()->route('web.catalog.products.import.show', $token)->withErrors($exception->errors())->withInput();
        }

        $request->session()->put(self::SESSION_KEY, [...$pending, 'rows' => $result['rows'], 'totals' => $result['totals']]);

        return redirect()
            ->route('web.catalog.products.import.show', $token)
            ->with('success', $result['mapped'] > 0
                ? number_format($result['mapped']).' new part type(s) now use existing types. The review has been updated.'
                : 'No changes: the remaining new part types will be created.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('web.catalog.products.import.create')
            ->with('success', 'Import cancelled. Nothing was saved.');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, CatalogProductImportService::TEMPLATE_HEADERS, ',', '"', '');
            fputcsv($handle, ['Head Lamp', 'Toyota Hilux 2016 Head Lamp - Left', '4', '45,000.00', '60,000.00', '75,000.00', '2'], ',', '"', '');
            fclose($handle);
        }, 'parts-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function pendingImport(Request $request, string $token): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return is_array($pending) && hash_equals((string) ($pending['token'] ?? ''), $token) ? $pending : null;
    }

    /**
     * Same shape as Laravel's validation JSON, plus where to go to start again.
     */
    private function jsonFailure(string $message, array $errors, string $redirect): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => $errors, 'redirect' => $redirect], 422);
    }

    private function percent(?float $percent): string
    {
        return $percent === null ? 'n/a' : number_format($percent, 1).'%';
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}
