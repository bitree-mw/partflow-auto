<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\InventoryDocument;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Services\InventoryDocumentService;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class August2026DemoSalesSeeder extends Seeder
{
    private const SALE_NOTE_PREFIX = 'August 2026 demo sale';

    private const STOCK_NOTE_PREFIX = 'August 2026 demo stock';

    public function run(
        InventoryDocumentService $inventoryDocumentService,
        PaymentService $paymentService
    ): void {
        $startDate = Carbon::create(2026, 8, 1, 0, 0, 0, config('app.timezone'));
        $endOfAugust = $startDate->copy()->endOfMonth();
        $endDate = Carbon::today()->min($endOfAugust);

        if ($endDate->lessThan($startDate)) {
            $this->command?->warn('August 2026 demo sales were not seeded because the date range has not started.');

            return;
        }

        $user = User::query()
            ->where('email', 'admin@partflow.test')
            ->where('is_active', true)
            ->first()
            ?? User::query()
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'System Administrator'))
                ->first();

        $sites = Site::query()
            ->active()
            ->where('type', '!=', 'warehouse')
            ->orderBy('id')
            ->get();

        if ($sites->isEmpty()) {
            $sites = Site::query()->active()->orderBy('id')->get();
        }

        $products = Product::query()
            ->active()
            ->where('default_selling_price', '>', 0)
            ->orderBy('product_code')
            ->limit(6)
            ->get();
        $customers = Contact::query()
            ->active()
            ->whereIn('contact_type', ['customer', 'both'])
            ->orderBy('id')
            ->get();
        $paymentAccounts = PaymentAccount::query()->active()->orderBy('id')->get();

        if (! $user || $sites->isEmpty() || $products->isEmpty()) {
            throw new RuntimeException('August demo sales require an active system administrator, selling site, and priced product.');
        }

        foreach ($sites as $site) {
            $this->seedStock(
                $inventoryDocumentService,
                $paymentService,
                $site,
                $products,
                $paymentAccounts->first(),
                $user,
                $startDate->copy()->subDay()->setTime(15, 0)
            );
        }

        $createdSales = 0;
        $day = $startDate->copy();

        while ($day->lessThanOrEqualTo($endDate)) {
            foreach ([0, 1] as $saleIndex) {
                $note = sprintf('%s | %s | %d', self::SALE_NOTE_PREFIX, $day->toDateString(), $saleIndex + 1);

                if (InventoryDocument::query()->where('document_type', 'sale')->where('notes', $note)->exists()) {
                    continue;
                }

                $site = $sites[($day->day + $saleIndex) % $sites->count()];
                $items = $this->saleItems($products, $day->day, $saleIndex);
                $saleDate = $day->copy()->setTime($saleIndex === 0 ? 9 : 13, $saleIndex === 0 ? 10 : 40);

                if ($saleDate->isFuture()) {
                    $saleDate = Carbon::now()->subMinutes(5 + ($saleIndex * 5));
                }

                $sale = $inventoryDocumentService->createSale([
                    'source_site_id' => $site->id,
                    'contact_id' => $customers->isEmpty()
                        ? null
                        : $customers[($day->day + $saleIndex) % $customers->count()]->id,
                    'document_date' => $saleDate,
                    'status' => 'completed',
                    'notes' => $note,
                    'items' => $items,
                ], $user);

                $paidRatio = [1.0, 0.65, 0.0, 1.0][($day->day + $saleIndex) % 4];
                $paymentAccount = $paymentAccounts->isEmpty()
                    ? null
                    : $paymentAccounts[($day->day + $saleIndex) % $paymentAccounts->count()];

                if ($paymentAccount && $paidRatio > 0) {
                    $paymentService->createForDocument($sale, [
                        'payment_account_id' => $paymentAccount->id,
                        'amount' => round((float) $sale->total_amount * $paidRatio, 2),
                        'payment_method' => $paymentAccount->account_type,
                        'payment_date' => $saleDate,
                        'transaction_reference' => sprintf('AUG26-%s-%d', $day->format('md'), $saleIndex + 1),
                        'notes' => 'August 2026 demo payment.',
                    ], $user);
                }

                $createdSales++;
            }

            $day->addDay();
        }

        $this->command?->info("August 2026 demo sales ready; {$createdSales} new sales created.");
    }

    private function seedStock(
        InventoryDocumentService $inventoryDocumentService,
        PaymentService $paymentService,
        Site $site,
        Collection $products,
        ?PaymentAccount $paymentAccount,
        User $user,
        Carbon $date
    ): void {
        $note = sprintf('%s | %s', self::STOCK_NOTE_PREFIX, $site->code);

        if (InventoryDocument::query()->where('document_type', 'purchase')->where('notes', $note)->exists()) {
            return;
        }

        $purchase = $inventoryDocumentService->createPurchase([
            'destination_site_id' => $site->id,
            'document_date' => $date,
            'status' => 'completed',
            'notes' => $note,
            'items' => $products->map(fn (Product $product): array => [
                'product_id' => $product->id,
                'quantity' => 500,
                'unit_cost' => max(
                    1,
                    (float) $product->default_purchase_price ?: round((float) $product->default_selling_price * 0.7, 2)
                ),
            ])->all(),
        ], $user);

        if (! $paymentAccount) {
            return;
        }

        $paymentService->createForDocument($purchase, [
            'payment_account_id' => $paymentAccount->id,
            'amount' => (float) $purchase->total_amount,
            'payment_method' => $paymentAccount->account_type,
            'payment_date' => $date,
            'transaction_reference' => "AUG26-STOCK-{$site->code}",
            'notes' => 'August 2026 demo stock payment.',
        ], $user);
    }

    private function saleItems(Collection $products, int $day, int $saleIndex): array
    {
        $firstProductIndex = ($day + $saleIndex) % $products->count();
        $items = [[
            'product_id' => $products[$firstProductIndex]->id,
            'quantity' => 1 + (($day + $saleIndex) % 3),
        ]];

        if ($products->count() > 1 && ($day + $saleIndex) % 3 !== 0) {
            $items[] = [
                'product_id' => $products[($firstProductIndex + 1) % $products->count()]->id,
                'quantity' => 1,
            ];
        }

        return $items;
    }
}
