<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Mission;
use App\Models\Setting;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Database\Seeder;

class GamificationSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put('agent_sales_target', '10000');

        // Daily missions (matches the agent-dashboard mockup).
        $missions = [
            ['follow_up_10',    'Follow up with 10 customers', 40, false],
            ['complete_booking','Complete 1 booking',          50, true],
            ['marketing_video', 'Upload 1 marketing video',    30, false],
            ['share_posts',     'Share 3 promotional posts',   20, false],
            ['daily_report',    'Submit daily activity report',10, false],
        ];
        foreach ($missions as $i => [$code, $title, $points, $auto]) {
            Mission::updateOrCreate(['code' => $code], [
                'title' => $title, 'points' => $points, 'frequency' => 'daily',
                'auto' => $auto, 'active' => true, 'sort' => $i,
            ]);
        }

        // Achievements (the 10 badges from the mockup).
        $achievements = [
            ['first_booking',  'First Booking',   '🏆', 'bookings_count',  1,      0],
            ['sales_10k',      'RM10K Sales',     '💰', 'sales_total',     10000,  1],
            ['sales_50k',      'RM50K Sales',     '🥉', 'sales_total',     50000,  2],
            ['customers_100',  '100 Customers',   '👥', 'customers_count', 100,    3],
            ['top_10',         'Top 10 Agent',    '🔟', 'rank_top',        10,     4],
            ['streak_30',      '30-Day Streak',   '🔥', 'streak_days',     30,     5],
            ['fast_closer',    'Fast Closer',     '⚡', 'bookings_count',  5,      6],
            ['top_3',          'Top 3 Agent',     '🥇', 'rank_top',        3,      7],
            ['followups_100',  '100 Follow-ups',  '📞', 'followups_count', 100,    8],
            ['referral_king',  'Referral King',   '🤝', 'referrals_count', 5,      9],
        ];
        foreach ($achievements as [$code, $name, $icon, $type, $value, $sort]) {
            Achievement::updateOrCreate(['code' => $code], [
                'name' => $name, 'icon' => $icon, 'criteria_type' => $type,
                'criteria_value' => $value, 'sort' => $sort,
            ]);
        }

        // Give the demo agent a starting streak + points so the UI is lively.
        $game = app(GamificationService::class);
        $demo = User::where('agent_code', 'BT-AG003')->first(); // Imran (deepest seller)
        if ($demo) {
            $streak = $game->streakFor($demo);
            $streak->update(['current' => 4, 'longest' => 12, 'last_active_date' => now()->subDay()->toDateString()]);
            $game->awardPoints($demo, 2480, 'seed', 'Starting balance (demo)');
            $game->evaluateAchievements($demo);
        }
    }
}
