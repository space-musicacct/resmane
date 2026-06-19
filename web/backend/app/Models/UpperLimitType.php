<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UpperLimitType extends Model
{
    use HasFactory, SoftDeletes;

    const PERCENTAGE_ID = 1;
    const FIXED_AMOUNT_ID = 2;

    protected $fillable = [
        'type_name',
    ];

    public function upperLimitSettings(): HasMany
    {
        return $this->hasMany(UpperLimitSetting::class);
    }
}
