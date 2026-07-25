<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = [
            ['Super Admin',   'super@bluetravel.com',    'super_admin'],
            ['HQ Manager',    'hq@bluetravel.com',       'hq'],
            ['Admin Officer', 'admin@bluetravel.com',    'admin'],
            ['John Rahman',   'agent@bluetravel.com',    'agent'],
            ['Aisyah Kamal',  'customer@bluetravel.com', 'customer'],
            ['Grand Hotel KL','provider@bluetravel.com', 'provider'],
        ];

        // Demo: the Admin Officer is a limited-access staff member (operations only, no finance/commission/company).
        $adminPerms = ['packages', 'customers', 'bookings', 'payments', 'tickets'];

        foreach ($accounts as [$name, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'        => $name,
                    'role'        => $role,
                    'status'      => 'active',
                    'permissions' => $role === 'admin' ? $adminPerms : null,
                    'password'    => bcrypt('password'),
                ]
            );
        }

        $this->call(CatalogSeeder::class);
        $this->call(CommissionSeeder::class);
        $this->call(GamificationSeeder::class);
        $this->call(MarketingSupportSeeder::class);
        $this->call(ShowcaseSeeder::class);
        $this->call(CustomerPortalSeeder::class);
    }
}
