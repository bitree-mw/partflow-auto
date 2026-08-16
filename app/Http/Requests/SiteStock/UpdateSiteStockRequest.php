<?php

namespace App\Http\Requests\SiteStock;

use App\Http\Requests\ApiRequest;
use App\Models\SiteStock;
use App\Services\SiteAccessService;

class UpdateSiteStockRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $siteStock = $this->route('site_stock');
        $user = $this->user();

        return $user
            && $siteStock instanceof SiteStock
            && in_array(
                $siteStock->site_id,
                app(SiteAccessService::class)->allowedSiteIds($user, SiteAccessService::ADJUST_STOCK),
                true
            );
    }

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
