<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'document_type',
        'contact_id',
        'source_site_id',
        'destination_site_id',
        'document_date',
        'status',
        'subtotal_amount',
        'discount_amount',
        'taxable_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'payment_status',
        'notes',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'datetime',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sourceSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'source_site_id');
    }

    public function destinationSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'destination_site_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        return $query->when($type, fn (Builder $query) => $query->where('document_type', $type));
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $query) => $query->where('status', $status));
    }

    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $query) => $query->whereDate('document_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('document_date', '<=', $to));
    }

    public function scopeForContact(Builder $query, ?int $contactId): Builder
    {
        return $query->when($contactId, fn (Builder $query) => $query->where('contact_id', $contactId));
    }

    public function scopeForSite(Builder $query, ?int $siteId): Builder
    {
        return $query->when($siteId, function (Builder $query) use ($siteId) {
            $query->where(function (Builder $query) use ($siteId) {
                $query->where('source_site_id', $siteId)
                    ->orWhere('destination_site_id', $siteId);
            });
        });
    }

    public function scopeForSites(Builder $query, ?array $siteIds): Builder
    {
        return $query->when($siteIds !== null, function (Builder $query) use ($siteIds) {
            $query
                ->where(function (Builder $query) {
                    $query->whereNotNull('source_site_id')
                        ->orWhereNotNull('destination_site_id');
                })
                ->where(function (Builder $query) use ($siteIds) {
                    $query->whereNull('source_site_id')
                        ->orWhereIn('source_site_id', $siteIds);
                })
                ->where(function (Builder $query) use ($siteIds) {
                    $query->whereNull('destination_site_id')
                        ->orWhereIn('destination_site_id', $siteIds);
                });
        });
    }
}
