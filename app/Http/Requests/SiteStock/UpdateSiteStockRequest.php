<?php

namespace App\Http\Requests\SiteStock;

use App\Http\Requests\ApiRequest;

class UpdateSiteStockRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'quantity_on_hand' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],

            'reserved_quantity' => [
                'sometimes',
                'required',
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
}
