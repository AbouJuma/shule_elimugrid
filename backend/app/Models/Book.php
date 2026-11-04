<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Book extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = ['school_id', 'title', 'author', 'isbn', 'category', 'total_copies', 'available_copies'];

    protected static function booted(): void
    {
        static::creating(function (Book $book) {
            $book->uuid ??= (string) Str::uuid();
        });
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function isFullyBorrowed(): bool
    {
        return $this->available_copies <= 0;
    }
}
