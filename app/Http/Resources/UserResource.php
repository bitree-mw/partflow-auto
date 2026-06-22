<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->whenLoaded('role', function () {
                return new RoleResource($this->role);
            }),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toDateTimeString(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at?->toDateTimeString(),
            'site_access' => $this->whenLoaded('siteAccesses', function () {
                return UserSiteAccessResource::collection($this->siteAccesses);
            }),
        ];
    }
}
