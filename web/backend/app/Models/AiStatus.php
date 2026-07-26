<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiStatus extends Model
{
    use SoftDeletes;

    public const int PENDING_ID = 1;

    public const int PROCESSING_ID = 2;

    public const int COMPLETED_ID = 3;

    public const int FAILED_ID = 4;
}
