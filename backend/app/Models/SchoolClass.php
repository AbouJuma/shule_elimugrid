<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Table is school_classes, not classes — "Class" is a reserved word and
 * can't be used as a PHP class name.
 */
class SchoolClass extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'school_classes';

    protected $fillable = ['school_id', 'campus_id', 'name', 'order'];

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject');
    }
}
