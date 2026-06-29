<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'car_make_id',
        'vehicle_model_id',
        'make',
        'make_code',
        'model',
        'model_code',
        'year',
        'engine_size',
        'variant_name',
        'country_of_origin',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function carMake(): BelongsTo
    {
        return $this->belongsTo(CarMake::class);
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function compatibleProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_compatibilities')
            ->withPivot('notes')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEngineSize(Builder $query, ?string $engineSize): Builder
    {
        return $query->when($engineSize, function (Builder $query) use ($engineSize) {
            $query->where('engine_size', 'like', "%{$engineSize}%");
        });
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query) use ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('make_code', 'like', "%{$search}%")
                    ->orWhere('model_code', 'like', "%{$search}%")
                    ->orWhere('country_of_origin', 'like', "%{$search}%")
                    ->orWhere('engine_size', 'like', "%{$search}%")
                    ->orWhere('variant_name', 'like', "%{$search}%");
            });
        });
    }

    public function scopeForMake(Builder $query, ?string $make): Builder
    {
        return $query->when($make, function (Builder $query) use ($make) {
            $query->where('make', 'like', "%{$make}%");
        });
    }

    public function scopeForCarMake(Builder $query, ?int $carMakeId): Builder
    {
        return $query->when($carMakeId, fn (Builder $query) => $query->where('car_make_id', $carMakeId));
    }

    public function scopeForVehicleModel(Builder $query, ?int $vehicleModelId): Builder
    {
        return $query->when($vehicleModelId, fn (Builder $query) => $query->where('vehicle_model_id', $vehicleModelId));
    }

    public function scopeYear(Builder $query, ?int $year): Builder
    {
        return $query->when($year, function (Builder $query) use ($year) {
            $query->where('year', $year);
        });
    }
}
