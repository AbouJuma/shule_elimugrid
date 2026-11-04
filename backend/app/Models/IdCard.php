<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IdCard extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'school_id', 'student_id', 'id_card_template_id', 'card_number',
        'verification_token', 'status', 'issued_date', 'revoked_at',
        'revocation_reason', 'issued_by',
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
        static::creating(function (IdCard $idCard) {
            $idCard->verification_token ??= Str::random(48);
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(IdCardTemplate::class, 'id_card_template_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
