<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KakeiboRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'purchase_date',
        'amount_type_id',
        'amount',
        'details',
        'kakeibo_default_category_id',
    ];

     public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amountType(): BelongsTo
    {
        return $this->belongsTo(AmountType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            KakeiboDefaultCategory::class,
            'kakeibo_default_category_id'
        );
    }
}
