<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetablePeriod extends Model
{
    use BelongsToTenant;

    protected $fillable = ['school_id', 'name', 'start_time', 'end_time', 'is_break', 'sort_order'];

    protected function casts(): array
    {
        return ['is_break' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function timeLabel(): string
    {
        return substr($this->start_time, 0, 5).' – '.substr($this->end_time, 0, 5);
    }
}
