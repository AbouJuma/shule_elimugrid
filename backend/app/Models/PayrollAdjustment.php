<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A one-off amount (arrears, bonus, advance recovery…) for one staff member in one month's payroll. */
class PayrollAdjustment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['school_id', 'staff_profile_id', 'period', 'name', 'type', 'amount', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
