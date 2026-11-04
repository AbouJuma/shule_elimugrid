<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReportCardRemark extends Model
{
    use BelongsToTenant;

    protected $fillable = ['school_id', 'student_id', 'term_id', 'class_teacher_remark', 'head_teacher_remark'];
}
