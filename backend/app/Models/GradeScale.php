<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeScale extends Model
{
    use BelongsToTenant;

    protected $fillable = ['school_id', 'grade', 'min_percent', 'remark', 'remark_sw'];
}
