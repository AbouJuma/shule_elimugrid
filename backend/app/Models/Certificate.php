<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'certificate_template_id', 'certificate_number',
        'verification_token', 'title', 'achievement', 'issued_date', 'status',
        'revoked_at', 'revocation_reason', 'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            // 48 chars of randomness — not derived from the id/number, so
            // it can't be enumerated or guessed from a known certificate.
            $certificate->verification_token ??= Str::random(48);
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }
}
