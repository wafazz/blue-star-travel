<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Mission;
use App\Models\Redemption;
use App\Services\GamificationService;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function __construct(
        private GamificationService $game,
        private LeaderboardService $leaderboard,
    ) {}

    public function checkin(Request $request)
    {
        $result = $this->game->checkIn($request->user());

        if ($result['already']) {
            return back()->with('ok', 'Already checked in today. Streak: ' . $result['streak'] . ' days 🔥');
        }
        $msg = "Checked in! +{$result['points']} pts · {$result['streak']}-day streak 🔥";
        if (! empty($result['reward'])) {
            $msg .= " · Unlocked {$result['reward']}!";
        }

        return back()->with('ok', $msg);
    }

    public function completeMission(Mission $mission, Request $request)
    {
        $done = $this->game->completeMission($request->user(), $mission);

        return back()->with('ok', $done ? "Mission complete! +{$mission->points} pts 🎉" : 'Mission already completed today.');
    }

    public function redeem(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:' . implode(',', array_keys(Redemption::CATALOG))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        [$cost, $cash] = Redemption::CATALOG[$data['type']];
        $this->game->redeem($request->user(), $data['type'], $cost, $cash, $data['note'] ?? null);

        return back()->with('ok', 'Redemption requested — pending approval.');
    }

    public function leaderboard(Request $request)
    {
        $period = $request->get('period', now()->format('Y-m'));
        $board = $this->leaderboard->monthly($period);
        $me = $board->firstWhere('user_id', $request->user()->id);

        return view('agent.gamification.leaderboard', compact('board', 'me', 'period'));
    }

    public function achievements(Request $request)
    {
        $user = $request->user();
        $achievements = Achievement::orderBy('sort')->get();
        $unlockedIds = $user->achievements()->pluck('achievements.id')->all();

        return view('agent.gamification.achievements', compact('achievements', 'unlockedIds'));
    }
}
