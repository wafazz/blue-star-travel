<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Coupon;
use App\Models\MarketingMaterial;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Seeder;

class MarketingSupportSeeder extends Seeder
{
    public function run(): void
    {
        Banner::updateOrCreate(['title' => 'Raya Umrah Promo 2026'], [
            'subtitle' => 'Book now — save up to RM500 per pax',
            'placement' => 'both', 'sort' => 0, 'active' => true,
        ]);
        Banner::updateOrCreate(['title' => 'Refer & Earn'], [
            'subtitle' => 'Invite an agent, earn RM50 + override commission',
            'placement' => 'agent', 'sort' => 1, 'active' => true,
        ]);

        Coupon::updateOrCreate(['code' => 'RAYA2026'], [
            'description' => 'Raya festive discount', 'discount_type' => 'percent',
            'discount_value' => 10, 'min_spend' => 1000, 'max_discount' => 500,
            'usage_limit' => 100, 'active' => true, 'expires_at' => now()->addMonths(3)->toDateString(),
        ]);
        Coupon::updateOrCreate(['code' => 'WELCOME50'], [
            'description' => 'RM50 off first booking', 'discount_type' => 'fixed',
            'discount_value' => 50, 'min_spend' => 500, 'usage_limit' => null, 'active' => true,
        ]);

        $materials = [
            ['Umrah 2026 Poster', 'poster', 'A4 promotional poster for Umrah packages'],
            ['Bali Getaway Reel', 'video', '30-second social media reel'],
            ['Company Brochure', 'brochure', 'Full package brochure PDF'],
            ['Instagram Story Pack', 'social', '5 story templates'],
        ];
        foreach ($materials as [$title, $cat, $desc]) {
            MarketingMaterial::updateOrCreate(['title' => $title], [
                'category' => $cat, 'description' => $desc, 'active' => true,
                'external_url' => 'https://bluetravel.example/materials',
            ]);
        }

        // A sample support ticket from an agent.
        $agent = User::where('agent_code', 'BT-AG002')->first();
        if ($agent && ! $agent->tickets()->exists()) {
            app(TicketService::class)->open($agent, [
                'subject'  => 'Commission for booking not showing',
                'category' => 'commission',
                'priority' => 'normal',
                'message'  => 'Hi, my commission for last week\'s Umrah booking hasn\'t appeared in my wallet yet. Could you check?',
            ]);
        }
    }
}
