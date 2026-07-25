<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use App\Services\TierService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Live launch seed — one super admin account and nothing else.
 * No demo packages, agents, customers, bookings or commission data.
 *
 *   php artisan migrate:fresh --seeder=ProductionSeeder --force
 *
 * Everything else (company profile, packages, commission levels, HQ share,
 * tier rules, payment gateway) is configured by HQ in /manage after login.
 */
class ProductionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@bstravel.agency'],
            [
                'name'        => 'Super Admin',
                'role'        => 'super_admin',
                'status'      => 'active',
                'permissions' => null,
                'password'    => bcrypt('MoHd20188!'),
            ]
        );

        // Baseline the tier qualification period to now, so the first tiers:requalify
        // run doesn't treat launch day as a period rollover.
        Setting::put('tier_period_processed', app(TierService::class)->currentPeriod()['key']);
    }
}
