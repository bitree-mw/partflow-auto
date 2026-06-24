<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PartFlowPurchaseSalesTestingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@partflow.test')->firstOrFail();
        $area23 = Site::where('code', 'A23')->firstOrFail();
        $oldTown = Site::where('code', 'OTN')->firstOrFail();
        $warehouse = Site::where('code', 'KNW')->firstOrFail();
        $supplier = Contact::where('code', 'SUP-001')->firstOrFail();
        $customer = Contact::where('code', 'CUS-001')->firstOrFail();
        $cash = PaymentAccount::where('account_type', 'cash')->firstOrFail();
        $bank = PaymentAccount::where('account_type', 'bank')->firstOrFail();
        $products = Product::query()->orderBy('product_code')->take(4)->get();

        $this->purchase('PUR-TEST-001', Carbon::now()->subDays(10), $supplier, $warehouse, $user, $bank, $products->take(3)->all());
        $this->purchase('PUR-TEST-002', Carbon::now()->subDays(4), $supplier, $oldTown, $user, $bank, $products->skip(1)->take(3)->all(), paidRatio: 0.55);
        $this->sale('POS-TEST-001', Carbon::now()->subDays(2), $customer, $area23, $user, $cash, $products->take(2)->all());
        $this->sale('POS-TEST-002', Carbon::now()->subDay(), $customer, $oldTown, $user, $cash, $products->skip(1)->take(2)->all(), paidRatio: 0.45);
    }

    private function purchase(string $number, Carbon $date, Contact $supplier, Site $site, User $user, PaymentAccount $paymentAccount, array $products, float $paidRatio = 1.0): void
    {
        $document = $this->document($number, 'purchase', $date, $supplier, $site, null, $user, $products, $paidRatio, 'Purchase seeded for testing.');
        $this->payment($document, $paymentAccount, $user, 'bank_transfer');
        $this->movements($document, $site, $user, 'purchase_in');
    }

    private function sale(string $number, Carbon $date, Contact $customer, Site $site, User $user, PaymentAccount $paymentAccount, array $products, float $paidRatio = 1.0): void
    {
        $document = $this->document($number, 'sale', $date, $customer, $site, null, $user, $products, $paidRatio, 'Sale seeded for testing.');
        $this->payment($document, $paymentAccount, $user, 'cash');
        $this->movements($document, $site, $user, 'sale_out');
    }

    private function document(string $number, string $type, Carbon $date, Contact $contact, Site $sourceSite, ?Site $destinationSite, User $user, array $products, float $paidRatio, string $notes): InventoryDocument
    {
        $document = InventoryDocument::updateOrCreate(
            ['document_number' => $number],
            [
                'document_type' => $type,
                'contact_id' => $contact->id,
                'source_site_id' => $sourceSite->id,
                'destination_site_id' => $destinationSite?->id,
                'document_date' => $date,
                'status' => 'completed',
                'notes' => $notes,
                'created_by' => $user->id,
                'approved_by' => $user->id,
            ]
        );

        $subtotal = 0;
        $tax = 0;
        $profit = 0;

        foreach ($products as $index => $product) {
            $quantity = $index + 2;
            $unitCost = (float) $product->default_purchase_price;
            $unitPrice = $type === 'purchase' ? $unitCost : (float) $product->default_selling_price;
            $lineTotal = $unitPrice * $quantity;
            $lineTax = round($lineTotal * 0.175 / 1.175, 2);
            $lineProfit = $type === 'sale' ? ($unitPrice - $unitCost) * $quantity : 0;

            $item = InventoryDocumentItem::updateOrCreate(
                ['inventory_document_id' => $document->id, 'product_id' => $product->id],
                [
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0,
                    'tax_rate' => 17.5,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                    'profit_amount' => $lineProfit,
                    'notes' => 'Seeded testing line.',
                ]
            );

            $subtotal += $lineTotal;
            $tax += $lineTax;
            $profit += $lineProfit;
        }

        $paid = round($subtotal * $paidRatio, 2);

        $document->update([
            'subtotal_amount' => $subtotal,
            'discount_amount' => 0,
            'taxable_amount' => $subtotal - $tax,
            'tax_amount' => $tax,
            'total_amount' => $subtotal,
            'paid_amount' => $paid,
            'balance_amount' => $subtotal - $paid,
            'payment_status' => $paid >= $subtotal ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
        ]);

        return $document->refresh();
    }

    private function payment(InventoryDocument $document, PaymentAccount $paymentAccount, User $user, string $method): void
    {
        if ((float) $document->paid_amount <= 0) {
            return;
        }

        Payment::updateOrCreate(
            ['inventory_document_id' => $document->id, 'transaction_reference' => "PAY-{$document->document_number}"],
            [
                'payment_account_id' => $paymentAccount->id,
                'amount' => $document->paid_amount,
                'payment_method' => $method,
                'payment_date' => $document->document_date,
                'received_by' => $user->id,
                'notes' => 'Seeded payment for transaction testing.',
            ]
        );
    }

    private function movements(InventoryDocument $document, Site $site, User $user, string $movementType): void
    {
        foreach ($document->items as $item) {
            $quantityChange = str_ends_with($movementType, '_out') ? -1 * $item->quantity : $item->quantity;

            StockMovement::updateOrCreate(
                ['inventory_document_id' => $document->id, 'inventory_document_item_id' => $item->id],
                [
                    'product_id' => $item->product_id,
                    'site_id' => $site->id,
                    'movement_type' => $movementType,
                    'quantity_change' => $quantityChange,
                    'balance_before' => 0,
                    'balance_after' => $quantityChange,
                    'reference_type' => 'inventory_document',
                    'reference_id' => $document->id,
                    'notes' => 'Seeded stock movement for testing.',
                    'created_by' => $user->id,
                ]
            );
        }
    }
}
