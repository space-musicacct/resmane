<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'kakeiboRecordId' => $this->kakeibo_record_id,
            'isAi' => $this->is_ai,
            'aiStatus' => $this->aiStatus ? [
                'id' => $this->aiStatus->id,
                'statusName' => $this->aiStatus->status_name,
            ] : null,
            'parentId' => $this->parent_id,
            'content' => $this->content,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
