<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SelfReview extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kakeibo_record_id',
        'review_comment',
    ];
}
