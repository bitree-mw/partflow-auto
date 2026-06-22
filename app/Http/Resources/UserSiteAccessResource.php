<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSiteAccessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),

            'site' => $this->whenLoaded('site', function () {
                return new SiteResource($this->site);
            }),

            'user_id' => $this->user_id,
            'site_id' => $this->site_id,
            'access_level' => $this->access_level,

            'permissions' => [
                'can_view_stock' => $this->can_view_stock,
                'can_make_sales' => $this->can_make_sales,
                'can_receive_stock' => $this->can_receive_stock,
                'can_transfer_stock' => $this->can_transfer_stock,
                'can_adjust_stock' => $this->can_adjust_stock,
            ],

            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
