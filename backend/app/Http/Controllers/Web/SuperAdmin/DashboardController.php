<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\PlatformDashboardService;
use App\Support\Pagination\PageSize;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, PlatformDashboardService $dashboard)
    {
        $this->authorize('schools.view');

        $schools = School::query()
            ->with('subscriptionPackage')
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('super-admin.dashboard', [
            'schools' => $schools,
            'platform' => $dashboard->summary(),
        ]);
    }
}
