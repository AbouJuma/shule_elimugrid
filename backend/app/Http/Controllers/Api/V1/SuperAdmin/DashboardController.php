<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SchoolResource;
use App\Services\PlatformDashboardService;

/** The same platform-wide numbers as the portal's Super Admin dashboard — see PlatformDashboardService. */
class DashboardController extends Controller
{
    public function index(PlatformDashboardService $dashboard)
    {
        $this->authorize('schools.view');

        $summary = $dashboard->summary();

        return response()->json(['data' => [
            'stats' => $summary['stats'],
            'onboarded_by_month' => $summary['onboardedByMonth'],
            'schools_by_package' => $summary['schoolsByPackage'],
            'recent_schools' => SchoolResource::collection($summary['recentSchools']),
        ]]);
    }
}
