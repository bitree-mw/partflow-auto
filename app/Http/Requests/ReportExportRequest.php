<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'report_type' => ['nullable', 'string', Rule::in([
                'sales', 'sale', 'sales-by-date-range',
                'purchases', 'purchase', 'purchases-by-date-range',
                'sale-returns', 'sale-return',
                'purchase-returns', 'purchase-return',
                'stock-transfers', 'stock-transfer', 'transfers', 'transfer',
                'stock-take-variance',
                'stock-movements', 'stock-movement', 'stock-movement-history',
                'payments', 'payment', 'payments-by-account',
                'expenses', 'profit-and-loss',
                'inventory', 'inventory-report', 'inventory-valuation',
                'current-stock', 'current-stock-by-site',
                'low-stock', 'low-stock-by-site',
                'out-of-stock', 'out-of-stock-products', 'stock-valuation',
                'creditors', 'creditor-report', 'creditor-balances',
                'debtors', 'debtor-report', 'debtor-balances', 'customer-balances',
                'most-selling-products', 'least-selling-products',
                'profit-by-product', 'profit-by-site',
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'status' => ['nullable', 'string', 'max:40'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }
}
