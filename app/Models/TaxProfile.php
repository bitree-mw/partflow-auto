<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'tax_type',
        'tax_rate',
        'price_mode',
        'is_exempt',
        'exemption_reason',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
            'is_exempt' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query) use ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('tax_type', 'like', "%{$search}%")
                    ->orWhere('price_mode', 'like', "%{$search}%");
            });
        });
    }

    public function isInclusive(): bool
    {
        return $this->price_mode === 'inclusive';
    }

    public function isExclusive(): bool
    {
        return $this->price_mode === 'exclusive';
    }

    public function isTaxExempt(): bool
    {
        return $this->is_exempt || $this->price_mode === 'exempt';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
