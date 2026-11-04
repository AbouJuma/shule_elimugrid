<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the Super Admin dashboard shows — platform-wide numbers across every
 * tenant. Deliberately cross-tenant: Student/User queries here rely on
 * BelongsToTenant's documented behaviour of seeing every school's rows when
 * no tenant is bound (true for a Super Admin session), so no explicit
 * ->allTenants() is needed — but School itself isn't tenant-scoped at all
 * (it IS the tenant), so its queries need no such note.
 */
class PlatformDashboardService
{
    /** @return array<string, mixed> */
    public function summary(): array
    {
        $schoolsByStatus = School::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'stats' => [
                'total_schools' => School::count(),
                'active_schools' => (int) ($schoolsByStatus['active'] ?? 0),
                'pending_schools' => (int) ($schoolsByStatus['pending'] ?? 0),
                'suspended_schools' => (int) ($schoolsByStatus['suspended'] ?? 0),
                'total_students' => Student::where('status', 'active')->count(),
                'total_staff' => $this->staffAccountCount(),
            ],
            'onboardedByMonth' => $this->onboardedByMonth(),
            'schoolsByPackage' => $this->schoolsByPackage(),
            'recentSchools' => School::with('subscriptionPackage')->latest()->limit(5)->get(),
        ];
    }

    /**
     * Users holding a real school role — not a parent or student login, and
     * not a platform account (Super Admin). A person with more than one role
     * is counted once.
     */
    protected function staffAccountCount(): int
    {
        return (int) DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereNotIn('roles.name', ['Parent', 'Student', config('roles.super_admin_role')])
            ->distinct()
            ->count('model_has_roles.model_id');
    }

    /**
     * Schools onboarded in each of the last 6 months, oldest first. Grouped in
     * PHP rather than a driver-specific date-format SQL function, so this
     * works identically on MySQL (real use) and SQLite (the test suite).
     */
    protected function onboardedByMonth(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => Carbon::now()->startOfMonth()->subMonths($i));

        $counts = School::query()->where('created_at', '>=', $months->first())->pluck('created_at')
            ->countBy(fn ($createdAt) => Carbon::parse($createdAt)->format('Y-m'));

        return $months->map(fn ($m) => ['label' => $m->format('M'), 'value' => (int) ($counts[$m->format('Y-m')] ?? 0)])->all();
    }

    protected function schoolsByPackage(): array
    {
        $counts = School::query()->selectRaw('subscription_package_id, count(*) as c')->groupBy('subscription_package_id')->pluck('c', 'subscription_package_id');
        $packages = SubscriptionPackage::query()->whereIn('id', $counts->keys()->filter())->pluck('name', 'id');

        $rows = $counts->map(fn ($c, $id) => ['label' => $id ? ($packages[$id] ?? __('Unknown')) : __('No package'), 'value' => $c])
            ->sortByDesc('value')->values()->all();

        return $rows;
    }
}
