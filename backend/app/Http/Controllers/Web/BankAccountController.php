<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Fees\StoreBankAccountRequest;
use App\Http\Requests\Fees\UpdateBankAccountRequest;
use App\Models\BankAccount;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class BankAccountController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('bank_accounts.view');

        return view('school-admin.bank-accounts.index', [
            'accounts' => BankAccount::query()->orderBy('bank_name')->paginate(PageSize::resolve($request))->withQueryString(),
        ]);
    }

    public function store(StoreBankAccountRequest $request, TenantContext $tenant)
    {
        BankAccount::create(['school_id' => $tenant->id(), ...$request->validated()]);

        return redirect()->route('school-admin.bank-accounts.index')->with('status', 'Bank account added.');
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount)
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? false;

        $bankAccount->update($data);

        return redirect()->route('school-admin.bank-accounts.index')->with('status', 'Bank account updated.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $this->authorize('bank_accounts.manage');

        $bankAccount->delete();

        return redirect()->route('school-admin.bank-accounts.index')->with('status', 'Bank account deleted.');
    }
}
