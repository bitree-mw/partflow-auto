<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleModel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'car_make_id',
        'name',
        'code',
        'year',
        'body_style',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function carMake(): BelongsTo
    {
        return $this->belongsTo(CarMake::class);
    }

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query) use ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('body_style', 'like', "%{$search}%")
                    ->orWhere('year', 'like', "%{$search}%");
            });
        });
    }
}
