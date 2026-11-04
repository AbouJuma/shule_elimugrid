<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PayrollRun extends Model
{
    use BelongsToTenant;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = ['school_id', 'period', 'status', 'created_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (PayrollRun $run) {
            $run->uuid ??= (string) Str::uuid();
        });
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function periodLabel(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)->translatedFormat('F Y');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
