<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'site_id',
        'movement_type',
        'quantity_change',
        'balance_before',
        'balance_after',
        'inventory_document_id',
        'inventory_document_item_id',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function inventoryDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class);
    }

    public function inventoryDocumentItem(): BelongsTo
    {
        return $this->belongsTo(InventoryDocumentItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForProduct(Builder $query, ?int $productId): Builder
    {
        return $query->when($productId, fn (Builder $query) => $query->where('product_id', $productId));
    }

    public function scopeForSite(Builder $query, ?int $siteId): Builder
    {
        return $query->when($siteId, fn (Builder $query) => $query->where('site_id', $siteId));
    }

    public function scopeForSites(Builder $query, ?array $siteIds): Builder
    {
        return $query->when($siteIds !== null, fn (Builder $query) => $query->whereIn('site_id', $siteIds));
    }

    public function scopeType(Builder $query, ?string $movementType): Builder
    {
        return $query->when($movementType, fn (Builder $query) => $query->where('movement_type', $movementType));
    }
}
