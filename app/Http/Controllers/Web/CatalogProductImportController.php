<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ConfirmCatalogProductImportRequest;
use App\Http\Requests\Web\PreviewCatalogProductImportRequest;
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
        ]);
    }

    public function preview(PreviewCatalogProductImportRequest $request): RedirectResponse
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
                ['label' => 'Part lines', 'value' => number_format($totals['lines']), 'detail' => number_format($totals['units']).' units in total'],
                ['label' => 'New parts', 'value' => number_format($totals['new_parts']), 'detail' => number_format($totals['new_types']).' new product types'],
                ['label' => 'Restocked parts', 'value' => number_format($totals['restocked_parts']), 'detail' => 'Prices updated from the sheet'],
                ['label' => 'Purchase total', 'value' => $currency.' '.$this->money($totals['total_cost']), 'detail' => 'Paid in full from the chosen account'],
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
                'line_total' => $this->money($row['line_total']),
                'is_new_type' => $row['is_new_type'],
                'status' => $row['existing_product_id'] ? 'Restock '.$row['existing_product_code'] : 'New part',
                'status_tone' => $row['existing_product_id'] ? 'warning' : 'success',
            ])->all(),
            'totalCost' => $currency.' '.$this->money($totals['total_cost']),
        ]);
    }

    public function store(ConfirmCatalogProductImportRequest $request): RedirectResponse
    {
        $token = $request->validated('import_token');
        $pending = $this->pendingImport($request, $token);

        if (! $pending) {
            return redirect()
                ->route('web.catalog.products.import.create')
                ->with('error', 'That import preview has expired or was already imported. Upload the file again.');
        }

        // Clear the preview before writing so a double submit cannot import the same sheet twice.
        $request->session()->forget(self::SESSION_KEY);

        try {
            $purchase = $this->importService->import(
                $pending['rows'],
                $pending['context'],
                $request->user(),
                $pending['file_name']
            );
        } catch (ValidationException $exception) {
            // Nothing was saved (the import runs in one transaction); the preview is gone, so start again.
            return redirect()
                ->route('web.catalog.products.import.create')
                ->withErrors($exception->errors())
                ->with('error', 'The import was not saved. Fix the problems below and upload the file again.');
        }

        return redirect()
            ->route('web.catalog.products.index')
            ->with('success', sprintf(
                'Imported %s part lines into stock. Purchase %s recorded and paid: %s %s.',
                number_format($pending['totals']['lines']),
                $purchase->document_number,
                config('services.partflow.base_currency', 'MWK'),
                $this->money((float) $purchase->total_amount)
            ));
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

    private function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}
