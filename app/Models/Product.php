<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code',
        'product_name',
        'car_model_id',
        'product_type_id',
        'fuel_type_id',
        'brand_id',
        'tax_profile_id',
        'part_country_of_origin',
        'main_image_path',
        'description',
        'pos_description',
        'default_purchase_price',
        'default_selling_price',
        'minimum_selling_price',
        'default_low_stock_level',
        'unit_name',
        'pack_size',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_purchase_price' => 'decimal:2',
            'default_selling_price' => 'decimal:2',
            'minimum_selling_price' => 'decimal:2',
            'pack_size' => 'decimal:2',
            'default_low_stock_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function fuelType(): BelongsTo
    {
        return $this->belongsTo(FuelType::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(ProductReference::class);
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(ProductCompatibility::class);
    }

    public function compatibleCarModels(): BelongsToMany
    {
        return $this->belongsToMany(CarModel::class, 'product_compatibilities')
            ->withPivot('notes')
            ->withTimestamps();
    }

    public function siteStocks(): HasMany
    {
        return $this->hasMany(SiteStock::class);
    }

    public function inventoryDocumentItems(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query) use ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('product_code', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('part_country_of_origin', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('pos_description', 'like', "%{$search}%")
                    ->orWhereHas('references', function (Builder $query) use ($search) {
                        $query->where('reference_value', 'like', "%{$search}%");
                    });
            });
        });
    }
}
