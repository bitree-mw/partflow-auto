<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'site_id',
        'quantity_on_hand',
        'reserved_quantity',
        'low_stock_level',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'reserved_quantity' => 'integer',
            'low_stock_level' => 'integer',
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

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->reserved_quantity);
    }

    public function getEffectiveLowStockLevelAttribute(): int
    {
        return $this->low_stock_level ?? $this->product?->default_low_stock_level ?? 0;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->available_quantity <= $this->effective_low_stock_level;
    }

    public function scopeForProduct(Builder $query, ?int $productId): Builder
    {
        return $query->when($productId, function (Builder $query) use ($productId) {
            $query->where('product_id', $productId);
        });
    }

    public function scopeForSite(Builder $query, ?int $siteId): Builder
    {
        return $query->when($siteId, function (Builder $query) use ($siteId) {
            $query->where('site_id', $siteId);
        });
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query
            ->whereRaw('COALESCE(low_stock_level, 0) > 0')
            ->whereRaw('(quantity_on_hand - reserved_quantity) <= COALESCE(low_stock_level, 0)');
    }
}
