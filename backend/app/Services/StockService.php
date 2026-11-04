<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place quantity_on_hand is ever written — every adjustment
 * goes through a row lock on the item (same pattern as the sequential
 * number generators) so concurrent stock-in/stock-out movements can
 * never race and leave the running balance wrong.
 */
class StockService
{
    public function recordMovement(
        InventoryItem $item,
        string $type,
        float $quantity,
        ?string $reason,
        User $recordedBy,
        ?Carbon $movedAt = null,
        ?PurchaseRequest $purchaseRequest = null,
    ): StockMovement {
        return DB::transaction(function () use ($item, $type, $quantity, $reason, $recordedBy, $movedAt, $purchaseRequest) {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            $delta = $type === 'out' ? -$quantity : $quantity;
            $newQuantity = (float) $locked->quantity_on_hand + $delta;

            if ($newQuantity < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Cannot remove {$quantity} {$locked->unit} — only {$locked->quantity_on_hand} {$locked->unit} of {$locked->name} in stock."],
                ]);
            }

            $locked->update(['quantity_on_hand' => $newQuantity]);

            return StockMovement::create([
                'school_id' => $item->school_id,
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => $quantity,
                'reason' => $reason,
                'purchase_request_id' => $purchaseRequest?->id,
                'recorded_by' => $recordedBy->id,
                'moved_at' => $movedAt ?? now(),
            ]);
        });
    }
}
