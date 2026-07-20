<?php

namespace Database\Seeders;

use App\Models\CommissionLevel;
use App\Models\Setting;
use App\Models\User;
use App\Services\AgentTreeService;
use Illuminate\Database\Seeder;

class CommissionSeeder extends Seeder
{
    public function run(): void
    {
        // Dynamic-depth payout levels (row count = cascade depth).
        foreach ([[1, 8, 'Direct'], [2, 4, 'Level 2'], [3, 2, 'Level 3']] as [$level, $percent, $label]) {
            CommissionLevel::updateOrCreate(['level' => $level], ['percent' => $percent, 'label' => $label, 'active' => true]);
        }

        Setting::put('agent_max_depth', '0'); // 0 = unlimited recruitment depth

        $tree = app(AgentTreeService::class);

        // Root agent (existing demo agent).
        $root = User::where('email', 'agent@bluetravel.com')->first();
        if (! $root) {
            return;
        }
        if (! $root->agent_code) {
            $root->update(['agent_code' => 'BT-AG001', 'agent_tier' => 'platinum']);
        }
        $tree->register($root, null);

        // A small downline chain: L1 under root, L2 under that L1.
        $chain = [
            ['Nadia Sofea',   'nadia.agent@bluetravel.com',   'BT-AG002', 'gold',   $root],
        ];
        $level1 = null;
        foreach ($chain as [$name, $email, $code, $tier, $referrer]) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'role' => 'agent', 'status' => 'active',
                'agent_code' => $code, 'agent_tier' => $tier,
                'password' => bcrypt('password'),
            ]);
            $tree->register($u, $referrer);
            $level1 = $u;
        }

        if ($level1) {
            $l2 = User::updateOrCreate(['email' => 'imran.agent@bluetravel.com'], [
                'name' => 'Imran Zaki', 'role' => 'agent', 'status' => 'active',
                'agent_code' => 'BT-AG003', 'agent_tier' => 'silver',
                'password' => bcrypt('password'),
            ]);
            $tree->register($l2, $level1);
        }
    }
}
