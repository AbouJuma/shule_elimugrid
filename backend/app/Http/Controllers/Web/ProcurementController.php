<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Inventory\StorePurchaseRequestRequest;
use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Services\ProcurementService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class ProcurementController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $canApprove = $request->user()->can('procurement.approve');

        $myRequests = PurchaseRequest::query()
            ->with('items')
            ->where('requested_by', $request->user()->id)
            ->latest()
            ->get();

        $queue = $canApprove
            ? PurchaseRequest::query()->with(['items', 'requestedBy'])
                ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(PageSize::resolve($request), ['*'], 'queue_page')
                ->withQueryString()
            : null;

        return view('school-admin.procurement.index', [
            'myRequests' => $myRequests,
            'queue' => $queue,
            'canApprove' => $canApprove,
            'inventoryItems' => InventoryItem::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StorePurchaseRequestRequest $request, TenantContext $tenant, ProcurementService $procurement)
    {
        $data = $request->validated();

        $procurement->request($tenant->assertBound(), $request->user(), $data['items'], $data['notes'] ?? null);

        return redirect()->route('school-admin.procurement.index')->with('status', 'Purchase request submitted.');
    }

    public function approve(PurchaseRequest $purchaseRequest, ProcurementService $procurement)
    {
        $this->authorize('procurement.approve');

        $procurement->approve($purchaseRequest, request()->user());

        return redirect()->route('school-admin.procurement.index')->with('status', 'Purchase request approved.');
    }

    public function reject(PurchaseRequest $purchaseRequest, ProcurementService $procurement)
    {
        $this->authorize('procurement.approve');

        $procurement->reject($purchaseRequest, request()->user());

        return redirect()->route('school-admin.procurement.index')->with('status', 'Purchase request rejected.');
    }

    public function receive(PurchaseRequest $purchaseRequest, ProcurementService $procurement)
    {
        $this->authorize('procurement.approve');

        $procurement->receive($purchaseRequest, request()->user());

        return redirect()->route('school-admin.procurement.index')->with('status', 'Purchase request marked received — stock updated.');
    }
}
