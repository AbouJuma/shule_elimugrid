<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

/**
 * Starting package tiers (spec section 49). Feature keys here are checked
 * exclusively through App\Services\FeatureService — never with scattered
 * if ($package == '...') checks in controllers.
 */
class SubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 0,
                'billing_cycle' => 'annually',
                'features' => ['students', 'academics', 'attendance'],
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'price' => 0,
                'billing_cycle' => 'annually',
                'features' => ['students', 'academics', 'attendance', 'fees', 'communication', 'reports'],
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'price' => 0,
                'billing_cycle' => 'annually',
                'features' => [
                    'students', 'academics', 'attendance', 'fees', 'communication', 'reports',
                    'transport', 'virtual_classroom', 'hr', 'inventory',
                ],
            ],
        ];

        foreach ($packages as $package) {
            SubscriptionPackage::updateOrCreate(['slug' => $package['slug']], $package);
        }
    }
}
