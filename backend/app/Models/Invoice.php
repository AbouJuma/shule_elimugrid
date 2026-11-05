<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'student_id', 'fee_structure_id', 'academic_year_id', 'term_id', 'bank_account_id',
        'invoice_number', 'control_number', 'total_amount', 'amount_paid', 'status', 'issued_date', 'due_date',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_date' => 'date',
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * withTrashed(): a fee invoice is a financial record — deleting the
     * student later (the roster's own soft delete, e.g. a duplicate entry)
     * must never make their invoice/payment history disappear or 500 the
     * invoices list for everyone else.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function gatewayTransactions(): HasMany
    {
        return $this->hasMany(PaymentGatewayTransaction::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * Never stored — always derived from total_amount/amount_paid so it
     * can never drift out of sync with the payment history.
     */
    public function balance(): string
    {
        return bcsub((string) $this->total_amount, (string) $this->amount_paid, 2);
    }
}
