<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Payslip extends Model
{
    use BelongsToTenant;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'payroll_run_id', 'staff_profile_id', 'basic_salary', 'total_allowances', 'total_deductions',
        'gross_pay', 'net_pay', 'employer_cost', 'payment_status', 'payment_method', 'payment_reference', 'paid_at', 'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2', 'total_allowances' => 'decimal:2', 'total_deductions' => 'decimal:2',
            'gross_pay' => 'decimal:2', 'net_pay' => 'decimal:2', 'employer_cost' => 'decimal:2', 'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payslip $payslip) {
            $payslip->uuid ??= (string) Str::uuid();
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
