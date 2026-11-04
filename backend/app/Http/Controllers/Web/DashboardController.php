<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Single entry point after login — routes each user to the dashboard
     * for their context rather than every screen needing to know how to
     * tell platform vs school users apart.
     */
    public function index(Request $request, DashboardService $dashboard)
    {
        $user = $request->user();

        if ($user->hasRole('Super Admin')) {
            return redirect()->route('super-admin.dashboard');
        }

        return view('school-admin.dashboard', ['user' => $user->load('roles'), 'd' => $dashboard->forUser($user)]);
    }
}
