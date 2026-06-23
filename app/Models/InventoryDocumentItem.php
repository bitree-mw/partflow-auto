<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDocumentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_document_id',
        'product_id',
        'quantity',
        'unit_cost',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'profit_amount',
        'system_quantity',
        'counted_quantity',
        'variance_quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'system_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'variance_quantity' => 'integer',
        ];
    }

    public function inventoryDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
