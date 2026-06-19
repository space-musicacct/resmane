<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UpperLimitSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'upperLimitTypeId' => $this->upper_limit_type_id,
            'upperLimitTypeName' => $this->upperLimitType->type_name,
            'maxValue' => $this->max_value,
            'aveMonthlyIncome' => $this->ave_monthly_income,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
