<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InventoryItem extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = ['school_id', 'name', 'category', 'inventory_category_id', 'unit', 'quantity_on_hand', 'reorder_level', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryItem $item) {
            $item->uuid ??= (string) Str::uuid();
        });
    }

    public function inventoryCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    public function categoryName(): ?string
    {
        return $this->inventoryCategory?->name ?? $this->category;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isBelowReorderLevel(): bool
    {
        return $this->reorder_level > 0 && $this->quantity_on_hand <= $this->reorder_level;
    }
}
