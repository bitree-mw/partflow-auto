<?php

namespace App\Http\Requests\SiteStock;

use App\Http\Requests\ApiRequest;
use App\Services\SiteAccessService;
use Illuminate\Validation\Rule;

class StoreSiteStockRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->canAccessSiteInput('site_id', SiteAccessService::ADJUST_STOCK);
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                Rule::unique('site_stocks', 'product_id')
                    ->where('site_id', $this->input('site_id')),
            ],

            'site_id' => [
                'required',
                'integer',
                'exists:sites,id',
            ],

            'quantity_on_hand' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'reserved_quantity' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'low_stock_level' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.unique' => 'This product already has stock recorded for this site.',
        ];
    }
}
