<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Student extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'school_id',
        'user_id',
        'campus_id',
        'school_class_id',
        'stream_id',
        'transport_route_id',
        'route_stop_id',
        'academic_year_id',
        'admission_number',
        'control_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'admission_date',
        'status',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Student $student) {
            $student->uuid ??= (string) Str::uuid();
        });

        // A control number is a permanent reference once assigned — like an
        // admission number, it never changes underneath the parent/school.
        static::updating(function (Student $student) {
            if ($student->getOriginal('control_number') !== null && $student->isDirty('control_number')) {
                $student->control_number = $student->getOriginal('control_number');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(StudentStatusChange::class)->latest('id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function idCards(): HasMany
    {
        return $this->hasMany(IdCard::class);
    }

    public function bookLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function hostelAllocations(): HasMany
    {
        return $this->hasMany(HostelAllocation::class);
    }

    public function onlineExamAttempts(): HasMany
    {
        return $this->hasMany(OnlineExamAttempt::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
