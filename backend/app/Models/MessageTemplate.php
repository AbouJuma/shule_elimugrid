<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MessageTemplate extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = ['school_id', 'name', 'category', 'event', 'locale', 'body'];

    /** A message the system sends by itself (its event is fixed; the school edits the wording). */
    public function isSystem(): bool
    {
        return $this->event !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (MessageTemplate $template) {
            $template->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * Replaces {token} placeholders with values from $data. Unknown
     * tokens are left as-is rather than silently blanked — a typo'd
     * placeholder should be visible in a test send, not disappear.
     */
    public function render(array $data): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($data) {
            return array_key_exists($matches[1], $data) ? (string) $data[$matches[1]] : $matches[0];
        }, $this->body);
    }
}
