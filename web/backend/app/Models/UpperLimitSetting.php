<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UpperLimitSetting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'upper_limit_type_id',
        'max_value',
        'ave_monthly_income',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upperLimitType(): BelongsTo
    {
        return $this->belongsTo(UpperLimitType::class);
    }
}
