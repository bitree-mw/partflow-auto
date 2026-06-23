<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'location',
        'phone',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function userAccesses(): HasMany
    {
        return $this->hasMany(UserSiteAccess::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_site_access')
            ->withPivot([
                'access_level',
                'can_view_stock',
                'can_make_sales',
                'can_receive_stock',
                'can_transfer_stock',
                'can_adjust_stock',
                'is_default',
                'is_active',
            ])
            ->withTimestamps();
    }

    public function siteStocks(): HasMany
    {
        return $this->hasMany(SiteStock::class);
    }

    public function sourceInventoryDocuments(): HasMany
    {
        return $this->hasMany(InventoryDocument::class, 'source_site_id');
    }

    public function destinationInventoryDocuments(): HasMany
    {
        return $this->hasMany(InventoryDocument::class, 'destination_site_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query) use ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        });
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        return $query->when($type, function (Builder $query) use ($type) {
            $query->where('type', $type);
        });
    }
}
