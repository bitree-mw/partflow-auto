<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSiteAccess extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_site_access';

    protected $fillable = [
        'user_id',
        'site_id',
        'access_level',
        'can_view_stock',
        'can_make_sales',
        'can_receive_stock',
        'can_transfer_stock',
        'can_adjust_stock',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'can_view_stock' => 'boolean',
            'can_make_sales' => 'boolean',
            'can_receive_stock' => 'boolean',
            'can_transfer_stock' => 'boolean',
            'can_adjust_stock' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId, function (Builder $query) use ($userId) {
            $query->where('user_id', $userId);
        });
    }

    public function scopeForSite(Builder $query, ?int $siteId): Builder
    {
        return $query->when($siteId, function (Builder $query) use ($siteId) {
            $query->where('site_id', $siteId);
        });
    }
}
