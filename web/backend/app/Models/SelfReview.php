<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SelfReview extends Model
{
    use HasFactory;
    use $table->softDeletes();
}
