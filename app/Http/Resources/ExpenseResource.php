<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'expense_category' => $this->whenLoaded('expenseCategory', function () {
                return new ExpenseCategoryResource($this->expenseCategory);
            }),

            'payment_account' => $this->whenLoaded('paymentAccount', function () {
                return $this->paymentAccount ? new PaymentAccountResource($this->paymentAccount) : null;
            }),

            'site' => $this->whenLoaded('site', function () {
                return $this->site ? new SiteResource($this->site) : null;
            }),

            'created_by_user' => $this->whenLoaded('creator', function () {
                return new UserResource($this->creator);
            }),

            'expense_category_id' => $this->expense_category_id,
            'payment_account_id' => $this->payment_account_id,
            'site_id' => $this->site_id,
            'expense_date' => $this->expense_date?->toDateTimeString(),
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'reference' => $this->reference,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
