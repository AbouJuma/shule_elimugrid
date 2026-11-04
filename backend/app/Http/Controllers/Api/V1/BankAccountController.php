<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\StoreBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use App\Support\Tenancy\TenantContext;

class BankAccountController extends Controller
{
    public function index()
    {
        $this->authorize('bank_accounts.view');

        return BankAccountResource::collection(
            BankAccount::query()->orderBy('bank_name')->get()
        );
    }

    public function store(StoreBankAccountRequest $request, TenantContext $tenant)
    {
        $account = BankAccount::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return response()->json(['data' => new BankAccountResource($account)], 201);
    }
}
