<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class VirtualClass extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id', 'title', 'description', 'school_class_id', 'subject_id',
        'scheduled_at', 'duration_minutes', 'room_name', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (VirtualClass $class) {
            $class->uuid ??= (string) Str::uuid();
            // Same unguessable-token approach as Certificate/IdCard's
            // verification_token — here it doubles as Jitsi's only
            // access control on the public server, not just an "avoid
            // sequential IDs" nicety.
            $class->room_name ??= 'elimugrid-'.Str::random(48);
        });
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function joinUrl(): string
    {
        return rtrim(config('services.jitsi.domain'), '/').'/'.$this->room_name;
    }

    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }

    /**
     * "scheduled"/"cancelled" is all the DB tracks — there's no webhook
     * from the public Jitsi server telling us a session actually started
     * or ended, so "upcoming / in progress / past" is derived from the
     * current time instead of a separately-maintained (and easily stale
     * or simply wrong) status field.
     */
    public function timeState(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        $now = now();

        if ($now->lt($this->scheduled_at)) {
            return 'upcoming';
        }

        return $now->lte($this->endsAt()) ? 'in_progress' : 'past';
    }
}
