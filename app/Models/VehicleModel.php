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
        'start_year',
        'end_year',
        'body_style',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
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
}
