<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_category_id',
        'payment_account_id',
        'site_id',
        'expense_date',
        'amount',
        'description',
        'reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $query) => $query->whereDate('expense_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('expense_date', '<=', $to));
    }
}
