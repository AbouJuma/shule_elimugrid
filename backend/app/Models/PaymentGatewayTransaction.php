<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentGatewayTransaction extends Model
{
    use BelongsToTenant, HasFactory;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'student_id', 'invoice_id', 'payment_id',
        'provider', 'gateway', 'external_id', 'msisdn', 'control_number',
        'amount', 'currency', 'status', 'gateway_transaction_id', 'failure_reason',
        'raw_request', 'raw_response', 'raw_callback',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_request' => 'array',
            'raw_response' => 'array',
            'raw_callback' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentGatewayTransaction $transaction) {
            $transaction->uuid ??= (string) Str::uuid();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
