<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function __construct(protected StockService $stock)
    {
    }

    /**
     * @param  array<int, array{inventory_item_id?: ?int, description: string, quantity: float, estimated_cost?: ?float}>  $items
     */
    public function request(School $school, User $requester, array $items, ?string $notes): PurchaseRequest
    {
        return DB::transaction(function () use ($school, $requester, $items, $notes) {
            $request = PurchaseRequest::create([
                'school_id' => $school->id,
                'requested_by' => $requester->id,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $request->items()->create($item);
            }

            return $request->load('items');
        });
    }

    public function approve(PurchaseRequest $request, User $approver): void
    {
        $this->assertStatus($request, 'pending');

        $request->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
    }

    public function reject(PurchaseRequest $request, User $approver): void
    {
        $this->assertStatus($request, 'pending');

        $request->update(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now()]);
    }

    /**
     * Marks an approved request received and, for every line item that
     * was matched to a catalog item (not a free-text-only description),
     * records a stock-in movement — the only point where a purchase
     * request actually touches quantity_on_hand.
     */
    public function receive(PurchaseRequest $request, User $receiver): void
    {
        $this->assertStatus($request, 'approved');

        DB::transaction(function () use ($request, $receiver) {
            $request->load('items.inventoryItem');

            foreach ($request->items as $item) {
                if ($item->inventoryItem) {
                    $this->stock->recordMovement(
                        $item->inventoryItem,
                        'in',
                        (float) $item->quantity,
                        "Received — purchase request #{$request->id}",
                        $receiver,
                        purchaseRequest: $request,
                    );
                }
            }

            $request->update(['status' => 'received', 'received_at' => now()]);
        });
    }

    protected function assertStatus(PurchaseRequest $request, string $expected): void
    {
        if ($request->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["This request must be {$expected} first (it is currently {$request->status})."],
            ]);
        }
    }
}
