<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StaffProfile extends Model
{
    use BelongsToTenant, HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'user_id', 'employee_number', 'job_title', 'department', 'employment_type',
        'date_joined', 'date_left', 'national_id', 'address', 'next_of_kin_name', 'next_of_kin_phone',
        'basic_salary', 'bank_name', 'bank_account_number',
    ];

    protected function casts(): array
    {
        return [
            'date_joined' => 'date',
            'date_left' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StaffProfile $profile) {
            $profile->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryComponents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StaffSalaryComponent::class);
    }
}
