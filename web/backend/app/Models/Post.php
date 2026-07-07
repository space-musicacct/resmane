<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'kakeibo_record_id',
        'ai_status_id',
        'parent_id',
        'is_ai',
        'content',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kakeiboRecord(): BelongsTo
    {
        return $this->belongsTo(KakeiboRecord::class);
    }

    public function aiStatus(): BelongsTo
    {
        return $this->belongsTo(AiStatus::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
