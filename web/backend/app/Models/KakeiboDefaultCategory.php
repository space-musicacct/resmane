<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KakeiboDefaultCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'amount_type_id',
        'category_name',
    ];

    public function amountType(): BelongsTo
    {
        return $this->belongsTo(AmountType::class);
    }
}
