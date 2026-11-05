<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BillPayPayment extends Model
{
    use BelongsToTenant, HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'student_id', 'bill_identifier', 'fsp_reference_id', 'pg_reference_id',
        'merchant_reference_id', 'amount', 'fsp_code', 'bill_type', 'status', 'message', 'raw_request',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_request' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BillPayPayment $model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /** The FIFO-split payment rows this one lump sum was divided into. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
