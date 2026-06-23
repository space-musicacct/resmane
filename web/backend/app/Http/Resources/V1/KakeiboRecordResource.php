<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KakeiboRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'purchaseDate' => $this->purchase_date,
            'amountTypeId' => $this->amount_type_id,
            'amountTypeName' => $this->amountType->type_name,
            'amount' => $this->amount,
            'details' => $this->details,
            'categoryId' => $this->kakeibo_default_category_id,
            'categoryName' => $this->category->category_name,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
