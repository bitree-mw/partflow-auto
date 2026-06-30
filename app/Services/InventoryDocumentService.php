<?php

namespace App\Services;

use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryDocumentService
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
        private readonly PaymentService $paymentService
    ) {}

    public function list(array $filters = []): Collection
    {
        return InventoryDocument::query()
            ->with($this->summaryRelations())
            ->type($filters['document_type'] ?? null)
            ->status($filters['status'] ?? null)
            ->forContact(isset($filters['contact_id']) ? (int) $filters['contact_id'] : null)
            ->forSite(isset($filters['site_id']) ? (int) $filters['site_id'] : null)
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->when(isset($filters['payment_status']), function ($query) use ($filters) {
                $query->where('payment_status', $filters['payment_status']);
            })
            ->latest('document_date')
            ->get();
    }

    public function listByType(string $documentType, array $filters = []): Collection
    {
        $filters['document_type'] = $documentType;

        return $this->list($filters);
    }

    public function show(InventoryDocument $inventoryDocument): InventoryDocument
    {
        return $inventoryDocument->load($this->detailRelations());
    }

    public function createPurchase(array $data, User $user): InventoryDocument
    {
        return DB::transaction(function () use ($data, $user) {
            $status = $data['status'] ?? 'completed';
            $document = $this->createBaseDocument('purchase', $data, $user, $status);
            $items = $this->createPricedItems($document, $data['items'], 'cost');

            if ($status === 'completed') {
                foreach ($items as $item) {
                    $this->stockMovementService->increase(
                        productId: $item->product_id,
                        siteId: $document->destination_site_id,
                        quantity: $item->quantity,
                        movementType: 'purchase_in',
                        createdBy: $user->id,
                        context: $this->movementContext($document, $item)
                    );

                    $item->product->forceFill([
                        'default_purchase_price' => $item->unit_cost,
                    ])->save();
                }
            }

            $this->applyInitialPayment($document, $data, $user);

            return $this->show($document->refresh());
        });
    }

    public function createSale(array $data, User $user): InventoryDocument
    {
        return DB::transaction(function () use ($data, $user) {
            $status = $data['status'] ?? 'completed';
            $document = $this->createBaseDocument('sale', $data, $user, $status);
            $items = $this->createPricedItems(
                document: $document,
                items: $data['items'],
                priceBasis: 'price',
                invoiceDiscount: (float) ($data['discount_amount'] ?? 0),
                profitMultiplier: 1
            );

            if ($status === 'completed') {
                foreach ($items as $item) {
                    $this->stockMovementService->decrease(
                        productId: $item->product_id,
                        siteId: $document->source_site_id,
                        quantity: $item->quantity,
                        movementType: 'sale_out',
                        createdBy: $user->id,
                        context: $this->movementContext($document, $item)
                    );
                }
            }

            $this->applyInitialPayment($document, $data, $user);

            return $this->show($document->refresh());
        });
    }

    public function createTransfer(array $data, User $user): InventoryDocument
    {
        return DB::transaction(function () use ($data, $user) {
            $status = $data['status'] ?? 'completed';
            $document = $this->createBaseDocument('transfer', $data, $user, $status);
            $items = $this->createNonFinancialQuantityItems($document, $data['items']);

            if ($status === 'completed') {
                foreach ($items as $item) {
                    $this->stockMovementService->decrease(
                        productId: $item->product_id,
                        siteId: $document->source_site_id,
                        quantity: $item->quantity,
                        movementType: 'transfer_out',
                        createdBy: $user->id,
                        context: $this->movementContext($document, $item)
                    );

                    $this->stockMovementService->increase(
                        productId: $item->product_id,
                        siteId: $document->destination_site_id,
                        quantity: $item->quantity,
                        movementType: 'transfer_in',
                        createdBy: $user->id,
                        context: $this->movementContext($document, $item)
                    );
                }
            }

            $this->paymentService->refreshDocumentPaymentStatus($document);

            return $this->show($document->refresh());
        });
    }

    public function createStockAdjustment(array $data, User $user): InventoryDocument
    {
        return DB::transaction(function () use ($data, $user) {
            $status = $data['status'] ?? 'approved';
            $data['source_site_id'] = $data['site_id'];

            $document = $this->createBaseDocument('adjustment', $data, $user, $status);
            $items = [];

            foreach ($data['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $quantityChange = (int) $row['quantity_change'];
                $items[] = $document->items()->create([
                    'product_id' => $product->id,
                    'quantity' => abs($quantityChange),
                    'unit_cost' => $row['unit_cost'] ?? $this->latestPurchaseCost($product),
                    'unit_price' => 0,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'line_total' => 0,
                    'profit_amount' => 0,
                    'variance_quantity' => $quantityChange,
                    'notes' => $row['notes'] ?? null,
                ])->load('product');
            }

            if ($status === 'approved') {
                foreach ($items as $item) {
                    $quantityChange = (int) $item->variance_quantity;

                    if ($quantityChange > 0) {
                        $this->stockMovementService->increase(
                            productId: $item->product_id,
                            siteId: $document->source_site_id,
                            quantity: $quantityChange,
                            movementType: 'adjustment_in',
                            createdBy: $user->id,
                            context: $this->movementContext($document, $item)
                        );
                    } else {
                        $this->stockMovementService->decrease(
                            productId: $item->product_id,
                            siteId: $document->source_site_id,
                            quantity: abs($quantityChange),
                            movementType: 'adjustment_out',
                            createdBy: $user->id,
                            context: $this->movementContext($document, $item)
                        );
                    }
                }
            }

            $this->paymentService->refreshDocumentPaymentStatus($document);

            return $this->show($document->refresh());
        });
    }

    public function createStockTake(array $data, User $user): InventoryDocument
    {
        return DB::transaction(function () use ($data, $user) {
            $status = $data['status'] ?? 'approved';
            $data['source_site_id'] = $data['site_id'];

            $document = $this->createBaseDocument('stock_take', $data, $user, $status);
            $items = [];

            foreach ($data['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $systemQuantity = $this->stockMovementService->currentQuantity($product->id, $document->source_site_id);
                $countedQuantity = (int) $row['counted_quantity'];
                $varianceQuantity = $countedQuantity - $systemQuantity;

                $items[] = $document->items()->create([
                    'product_id' => $product->id,
                    'quantity' => abs($varianceQuantity),
                    'unit_cost' => $row['unit_cost'] ?? $this->latestPurchaseCost($product),
                    'unit_price' => 0,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'line_total' => 0,
                    'profit_amount' => 0,
                    'system_quantity' => $systemQuantity,
                    'counted_quantity' => $countedQuantity,
                    'variance_quantity' => $varianceQuantity,
                    'notes' => $row['notes'] ?? null,
                ])->load('product');
            }

            if ($status === 'approved') {
                foreach ($items as $item) {
                    $this->stockMovementService->adjustToCount(
                        productId: $item->product_id,
                        siteId: $document->source_site_id,
                        countedQuantity: $item->counted_quantity,
                        createdBy: $user->id,
                        context: $this->movementContext($document, $item)
                    );
                }
            }

            $this->paymentService->refreshDocumentPaymentStatus($document);

            return $this->show($document->refresh());
        });
    }

    public function createSaleReturn(array $data, User $user): InventoryDocument
    {
        return DB::transaction(function () use ($data, $user) {
            $status = $data['status'] ?? 'completed';
            $document = $this->createBaseDocument('sale_return', $data, $user, $status);
            $items = $this->createPricedItems(
                document: $document,
                items: $data['items'],
                priceBasis: 'price',
                invoiceDiscount: (float) ($data['discount_amount'] ?? 0),
                profitMultiplier: -1
            );

            if ($status === 'completed') {
                foreach ($items as $item) {
                    $this->stockMovementService->increase(
                        productId: $item->product_id,
                        siteId: $document->destination_site_id,
                        quantity: $item->quantity,
                        movementType: 'sale_return_in',
                        createdBy: $user->id,
                        context: $this->movementContext($document, $item)
                    );
                }
            }

            $this->paymentService->refreshDocumentPaymentStatus($document);

            return $this->show($document->refresh());
        });
    }

    public function createPurchaseReturn(array $data, User $user): InventoryDocument
    {
        return DB::transaction(function () use ($data, $user) {
            $status = $data['status'] ?? 'completed';
            $document = $this->createBaseDocument('purchase_return', $data, $user, $status);
            $items = $this->createPricedItems(
                document: $document,
                items: $data['items'],
                priceBasis: 'cost',
                invoiceDiscount: (float) ($data['discount_amount'] ?? 0)
            );

            if ($status === 'completed') {
                foreach ($items as $item) {
                    $this->stockMovementService->decrease(
                        productId: $item->product_id,
                        siteId: $document->source_site_id,
                        quantity: $item->quantity,
                        movementType: 'purchase_return_out',
                        createdBy: $user->id,
                        context: $this->movementContext($document, $item)
                    );
                }
            }

            $this->paymentService->refreshDocumentPaymentStatus($document);

            return $this->show($document->refresh());
        });
    }

    private function createBaseDocument(
        string $documentType,
        array $data,
        User $user,
        string $status
    ): InventoryDocument {
        return InventoryDocument::create([
            'document_number' => $this->generateDocumentNumber($documentType),
            'document_type' => $documentType,
            'contact_id' => $data['contact_id'] ?? null,
            'source_site_id' => $data['source_site_id'] ?? null,
            'destination_site_id' => $data['destination_site_id'] ?? null,
            'document_date' => $data['document_date'] ?? now(),
            'status' => $status,
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'taxable_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'balance_amount' => 0,
            'payment_status' => 'unpaid',
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
            'approved_by' => $status === 'approved' ? $user->id : null,
        ]);
    }

    private function createPricedItems(
        InventoryDocument $document,
        array $items,
        string $priceBasis,
        float $invoiceDiscount = 0,
        int $profitMultiplier = 0
    ): array {
        $prepared = [];
        $subtotalAmount = 0;
        $itemDiscountAmount = 0;
        $netBeforeInvoiceDiscount = 0;

        foreach ($items as $row) {
            $product = Product::query()
                ->with('taxProfile')
                ->findOrFail($row['product_id']);

            $quantity = (int) $row['quantity'];
            $unitCost = round((float) ($row['unit_cost'] ?? $this->latestPurchaseCost($product)), 2);
            $unitPrice = round((float) ($row['unit_price'] ?? $product->default_selling_price), 2);
            $lineUnitAmount = $priceBasis === 'cost' ? $unitCost : $unitPrice;
            $lineSubtotal = round($quantity * $lineUnitAmount, 2);
            $lineDiscount = round((float) ($row['discount_amount'] ?? 0), 2);
            $lineNetBeforeInvoiceDiscount = max(0, round($lineSubtotal - $lineDiscount, 2));

            $prepared[] = [
                'row' => $row,
                'product' => $product,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'item_discount' => $lineDiscount,
                'net_before_invoice_discount' => $lineNetBeforeInvoiceDiscount,
            ];

            $subtotalAmount += $lineSubtotal;
            $itemDiscountAmount += $lineDiscount;
            $netBeforeInvoiceDiscount += $lineNetBeforeInvoiceDiscount;
        }

        $invoiceDiscount = min(round($invoiceDiscount, 2), round($netBeforeInvoiceDiscount, 2));
        $allocatedInvoiceDiscount = 0;
        $documentItems = [];
        $taxableAmount = 0;
        $taxAmount = 0;
        $totalAmount = 0;

        foreach ($prepared as $index => $line) {
            $isLast = $index === array_key_last($prepared);
            $invoiceDiscountShare = $netBeforeInvoiceDiscount > 0
                ? round($invoiceDiscount * ($line['net_before_invoice_discount'] / $netBeforeInvoiceDiscount), 2)
                : 0;

            if ($isLast) {
                $invoiceDiscountShare = round($invoiceDiscount - $allocatedInvoiceDiscount, 2);
            }

            $allocatedInvoiceDiscount += $invoiceDiscountShare;

            $totalLineDiscount = round($line['item_discount'] + $invoiceDiscountShare, 2);
            $lineTaxBase = max(0, round($line['line_subtotal'] - $totalLineDiscount, 2));
            $taxProfile = $this->resolveTaxProfile($line['row']['tax_profile_id'] ?? null, $line['product']);
            $tax = $this->calculateTax($lineTaxBase, $taxProfile);
            $lineProfit = $profitMultiplier === 0
                ? 0
                : round(($tax['taxable_amount'] - ($line['unit_cost'] * $line['quantity'])) * $profitMultiplier, 2);

            $documentItems[] = $document->items()->create([
                'product_id' => $line['product']->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $totalLineDiscount,
                'tax_rate' => $taxProfile ? $taxProfile->tax_rate : 0,
                'tax_amount' => $tax['tax_amount'],
                'line_total' => $tax['line_total'],
                'profit_amount' => $lineProfit,
                'notes' => $line['row']['notes'] ?? null,
            ])->load('product');

            $taxableAmount += $tax['taxable_amount'];
            $taxAmount += $tax['tax_amount'];
            $totalAmount += $tax['line_total'];
        }

        $this->setDocumentTotals(
            document: $document,
            subtotalAmount: $subtotalAmount,
            discountAmount: $itemDiscountAmount + $invoiceDiscount,
            taxableAmount: $taxableAmount,
            taxAmount: $taxAmount,
            totalAmount: $totalAmount
        );

        return $documentItems;
    }

    private function createNonFinancialQuantityItems(InventoryDocument $document, array $items): array
    {
        $documentItems = [];

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);

            $documentItems[] = $document->items()->create([
                'product_id' => $product->id,
                'quantity' => (int) $row['quantity'],
                'unit_cost' => $row['unit_cost'] ?? $this->latestPurchaseCost($product),
                'unit_price' => 0,
                'discount_amount' => 0,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'line_total' => 0,
                'profit_amount' => 0,
                'notes' => $row['notes'] ?? null,
            ])->load('product');
        }

        $this->setDocumentTotals($document, 0, 0, 0, 0, 0);

        return $documentItems;
    }

    private function latestPurchaseCost(Product $product): float
    {
        $cost = InventoryDocumentItem::query()
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->where('inventory_document_items.product_id', $product->id)
            ->where('inventory_documents.document_type', 'purchase')
            ->whereIn('inventory_documents.status', ['completed', 'approved'])
            ->where('inventory_document_items.unit_cost', '>', 0)
            ->latest('inventory_documents.document_date')
            ->latest('inventory_document_items.id')
            ->value('inventory_document_items.unit_cost');

        return (float) ($cost ?? $product->default_purchase_price ?? 0);
    }

    private function setDocumentTotals(
        InventoryDocument $document,
        float $subtotalAmount,
        float $discountAmount,
        float $taxableAmount,
        float $taxAmount,
        float $totalAmount
    ): void {
        $totalAmount = round($totalAmount, 2);

        $document->forceFill([
            'subtotal_amount' => round($subtotalAmount, 2),
            'discount_amount' => round($discountAmount, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'balance_amount' => $totalAmount,
            'payment_status' => $totalAmount > 0 ? 'unpaid' : 'paid',
        ])->save();
    }

    private function calculateTax(float $amount, ?TaxProfile $taxProfile): array
    {
        if (! $taxProfile || $taxProfile->isTaxExempt() || $taxProfile->tax_rate <= 0 || $taxProfile->price_mode === 'none') {
            return [
                'taxable_amount' => round($amount, 2),
                'tax_amount' => 0,
                'line_total' => round($amount, 2),
            ];
        }

        $rate = (float) $taxProfile->tax_rate / 100;

        if ($taxProfile->isInclusive()) {
            $taxableAmount = round($amount / (1 + $rate), 2);
            $taxAmount = round($amount - $taxableAmount, 2);

            return [
                'taxable_amount' => $taxableAmount,
                'tax_amount' => $taxAmount,
                'line_total' => round($amount, 2),
            ];
        }

        $taxableAmount = round($amount, 2);
        $taxAmount = round($taxableAmount * $rate, 2);

        return [
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'line_total' => round($taxableAmount + $taxAmount, 2),
        ];
    }

    private function resolveTaxProfile(?int $taxProfileId, Product $product): ?TaxProfile
    {
        if ($taxProfileId) {
            return TaxProfile::find($taxProfileId);
        }

        if ($product->taxProfile) {
            return $product->taxProfile;
        }

        return TaxProfile::query()
            ->active()
            ->default()
            ->first();
    }

    private function applyInitialPayment(InventoryDocument $document, array $data, User $user): void
    {
        if (! empty($data['payment']['amount'])) {
            $this->paymentService->createForDocument($document, $data['payment'], $user);

            return;
        }

        $this->paymentService->refreshDocumentPaymentStatus($document);
    }

    private function movementContext(InventoryDocument $document, InventoryDocumentItem $item): array
    {
        return [
            'inventory_document_id' => $document->id,
            'inventory_document_item_id' => $item->id,
            'notes' => $item->notes,
        ];
    }

    private function generateDocumentNumber(string $documentType): string
    {
        $prefix = match ($documentType) {
            'purchase' => 'PUR',
            'sale' => 'SAL',
            'transfer' => 'TRF',
            'sale_return' => 'SRT',
            'purchase_return' => 'PRT',
            'adjustment' => 'ADJ',
            'stock_take' => 'STK',
            'reservation' => 'RSV',
            default => 'DOC',
        };

        do {
            $documentNumber = $prefix.'-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (InventoryDocument::query()->where('document_number', $documentNumber)->exists());

        return $documentNumber;
    }

    private function summaryRelations(): array
    {
        return [
            'contact',
            'sourceSite',
            'destinationSite',
            'creator',
            'approver',
        ];
    }

    private function detailRelations(): array
    {
        return [
            'contact',
            'sourceSite',
            'destinationSite',
            'creator',
            'approver',
            'items.product.carModel',
            'items.product.partType',
            'items.product.fuelType',
            'items.product.brand',
            'items.product.taxProfile',
            'items.product.references',
            'items.product.compatibilities.carModel',
            'payments.paymentAccount',
            'payments.receiver',
            'stockMovements.product.carModel',
            'stockMovements.product.partType',
            'stockMovements.product.fuelType',
            'stockMovements.product.brand',
            'stockMovements.product.taxProfile',
            'stockMovements.product.references',
            'stockMovements.product.compatibilities.carModel',
            'stockMovements.site',
        ];
    }
}
