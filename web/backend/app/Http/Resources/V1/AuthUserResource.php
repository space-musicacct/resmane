<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loginId' => $this->login_id,
            'email' => $this->email,
            'name' => $this->name,
            'createdAt' => $this->created_at,
        ];
    }
}
