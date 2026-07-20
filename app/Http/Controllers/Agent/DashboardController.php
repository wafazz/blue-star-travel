<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Banner;
use App\Models\Booking;
use App\Models\Checkin;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\Mission;
use App\Models\MissionCompletion;
use App\Models\Setting;
use App\Services\AgentTreeService;
use App\Services\GamificationService;
use App\Services\LeaderboardService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private LeaderboardService $leaderboard,
        private GamificationService $game,
        private WalletService $walletSvc,
        private AgentTreeService $tree,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $weekStart = $today->copy()->startOfWeek();

        $rankInfo = $this->leaderboard->rankOf($user);

        $paidBookings = Booking::where('agent_id', $user->id)->whereIn('status', ['confirmed', 'completed']);

        $achievedThisMonth = (float) (clone $paidBookings)->where('created_at', '>=', $monthStart)->sum('paid_amount');
        $salesTarget = (float) Setting::get('agent_sales_target', 10000);
        $targetPct = $salesTarget > 0 ? min(100, round($achievedThisMonth / $salesTarget * 100)) : 0;

        $monthlyCommission = (float) Commission::where('earner_id', $user->id)
            ->whereIn('status', ['approved', 'paid'])
            ->where('period', $today->format('Y-m'))->sum('amount');

        $wallet = $this->walletSvc->walletFor($user);

        // Missions today
        $missions = Mission::where('active', true)->orderBy('sort')->get();
        $doneToday = MissionCompletion::where('user_id', $user->id)
            ->whereDate('period_date', $today)->pluck('mission_id')->all();
        $missionPointsAvailable = $missions->whereNotIn('id', $doneToday)->sum('points');

        // Check-in / streak
        $streak = $this->game->streakFor($user);
        $checkedInToday = Checkin::where('user_id', $user->id)->whereDate('checkin_date', $today)->exists();

        // Leaderboard (top 5 + me)
        $board = $this->leaderboard->monthly();
        $topBoard = $board->take(5);
        $myRow = $board->firstWhere('user_id', $user->id);

        // Achievements
        $totalAchievements = Achievement::count();
        $unlockedIds = $user->achievements()->pluck('achievements.id')->all();

        $stats = [
            'today_sales'    => (float) (clone $paidBookings)->whereDate('created_at', $today)->sum('paid_amount'),
            'week_sales'     => (float) (clone $paidBookings)->where('created_at', '>=', $weekStart)->sum('paid_amount'),
            'month_bookings' => (clone $paidBookings)->where('created_at', '>=', $monthStart)->count(),
            'customers'      => Customer::where('agent_id', $user->id)->count(),
        ];

        $attention = [
            'awaiting' => Booking::where('agent_id', $user->id)->where('status', 'waiting_provider_confirmation')->count(),
            'unpaid'   => Booking::where('agent_id', $user->id)->whereIn('status', ['confirmed', 'pending_verification'])
                            ->whereColumn('paid_amount', '<', 'total_amount')->count(),
        ];

        $unreadCount = $user->unreadNotificationsCount();
        $banner = Banner::live('agent')->first();

        return view('agent.dashboard', compact(
            'user', 'rankInfo', 'achievedThisMonth', 'salesTarget', 'targetPct', 'monthlyCommission',
            'wallet', 'missions', 'doneToday', 'missionPointsAvailable', 'streak', 'checkedInToday',
            'topBoard', 'myRow', 'totalAchievements', 'unlockedIds', 'stats', 'attention',
            'unreadCount', 'banner'
        ));
    }
}
