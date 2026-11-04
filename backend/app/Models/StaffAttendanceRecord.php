<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendanceRecord extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['school_id', 'user_id', 'date', 'status', 'remarks', 'source', 'marked_by'];

    // Deliberately NOT cast to 'date' — same reasoning as AttendanceRecord:
    // keeps the stored value byte-identical to the plain Y-m-d string the
    // service's updateOrCreate() searches by.

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
