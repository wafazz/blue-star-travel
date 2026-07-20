<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Checkin;
use App\Models\Mission;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GamificationService
{
    public function __construct(
        private LeaderboardService $leaderboard,
        private AgentTreeService $tree,
    ) {}

    // ---- Points ledger ---------------------------------------------------

    public function awardPoints(User $user, int $points, string $source, string $description): void
    {
        if ($points <= 0) {
            return;
        }
        DB::transaction(function () use ($user, $points, $source, $description) {
            $user->increment('reward_points', $points);
            $user->refresh();
            PointTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'earn',
                'points'        => $points,
                'balance_after' => $user->reward_points,
                'source'        => $source,
                'description'   => $description,
            ]);
        });

        $this->evaluateAchievements($user);
    }

    public function spendPoints(User $user, int $points, string $source, string $description): void
    {
        if ($points > (int) $user->reward_points) {
            throw ValidationException::withMessages(['points' => 'Not enough reward points.']);
        }
        DB::transaction(function () use ($user, $points, $source, $description) {
            $user->decrement('reward_points', $points);
            $user->refresh();
            PointTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'redeem',
                'points'        => -$points,
                'balance_after' => $user->reward_points,
                'source'        => $source,
                'description'   => $description,
            ]);
        });
    }

    // ---- Daily check-in + streak ----------------------------------------

    public function checkIn(User $user): array
    {
        $today = today();
        if (Checkin::where('user_id', $user->id)->whereDate('checkin_date', $today)->exists()) {
            return ['already' => true, 'points' => 0, 'streak' => $this->streakFor($user)->current];
        }

        return DB::transaction(function () use ($user, $today) {
            $streak = $this->streakFor($user);
            $yesterday = $today->copy()->subDay();

            if ($streak->last_active_date && $streak->last_active_date->isSameDay($yesterday)) {
                $streak->current += 1;
            } else {
                $streak->current = 1;
            }
            $streak->longest = max($streak->longest, $streak->current);
            $streak->last_active_date = $today;
            $streak->save();

            $day = $streak->current;
            $points = $day >= 3 ? 30 : $day * 10;
            $reward = null;
            if ($day == 7)  { $points += 50;  $reward = 'Travel Voucher'; }
            if ($day == 14) { $points += 100; $reward = 'Bonus Reward'; }
            if ($day == 30) { $points += 300; $reward = 'Special Reward'; }

            Checkin::create([
                'user_id'      => $user->id,
                'checkin_date' => $today,
                'day_number'   => $day,
                'points'       => $points,
                'reward'       => $reward,
            ]);

            $this->awardPoints($user, $points, 'checkin', "Daily check-in day {$day}" . ($reward ? " · {$reward}" : ''));

            return ['already' => false, 'points' => $points, 'streak' => $day, 'reward' => $reward];
        });
    }

    public function streakFor(User $user): Streak
    {
        return Streak::firstOrCreate(['user_id' => $user->id]);
    }

    // ---- Missions --------------------------------------------------------

    public function completeMission(User $user, Mission $mission): bool
    {
        if (! $mission->active) {
            return false;
        }
        $periodDate = $mission->frequency === 'weekly' ? today()->startOfWeek()->toDateString() : today()->toDateString();

        $completion = \App\Models\MissionCompletion::firstOrNew([
            'mission_id'  => $mission->id,
            'user_id'     => $user->id,
            'period_date' => $periodDate,
        ]);
        if ($completion->exists) {
            return false; // idempotent — already done this period
        }

        $completion->fill(['points_awarded' => $mission->points, 'completed_at' => now()])->save();
        $this->awardPoints($user, $mission->points, 'mission', "Mission: {$mission->title}");

        return true;
    }

    /** Auto-complete a mission by its code (fired from a system event). */
    public function completeMissionByCode(User $user, string $code): void
    {
        $mission = Mission::where('code', $code)->where('active', true)->first();
        if ($mission) {
            $this->completeMission($user, $mission);
        }
    }

    // ---- Redemptions -----------------------------------------------------

    public function redeem(User $user, string $type, int $pointsCost, float $cashValue = 0, ?string $note = null): Redemption
    {
        $this->spendPoints($user, $pointsCost, 'redemption', 'Redeemed: ' . (Redemption::TYPES[$type] ?? $type));

        return Redemption::create([
            'redemption_no' => 'RD-' . now()->format('Y') . '-' . strtoupper(Str::random(6)),
            'user_id'       => $user->id,
            'type'          => $type,
            'points_cost'   => $pointsCost,
            'cash_value'    => $cashValue,
            'status'        => 'pending',
            'note'          => $note,
        ]);
    }

    // ---- Achievements ----------------------------------------------------

    public function evaluateAchievements(User $user): array
    {
        $unlocked = [];
        $already = DB::table('agent_achievements')->where('user_id', $user->id)->pluck('achievement_id')->all();

        foreach (Achievement::whereNotIn('id', $already)->get() as $ach) {
            if ($this->meetsCriteria($user, $ach)) {
                DB::table('agent_achievements')->insert([
                    'achievement_id' => $ach->id,
                    'user_id'        => $user->id,
                    'unlocked_at'    => now(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $unlocked[] = $ach;
            }
        }

        return $unlocked;
    }

    private function meetsCriteria(User $user, Achievement $ach): bool
    {
        $v = (int) $ach->criteria_value;

        return match ($ach->criteria_type) {
            'bookings_count'  => $user->agentBookings()->whereIn('status', ['confirmed', 'completed'])->count() >= $v,
            'sales_total'     => (float) $user->agentBookings()->whereIn('status', ['confirmed', 'completed'])->sum('paid_amount') >= $v,
            'customers_count' => \App\Models\Customer::where('agent_id', $user->id)->count() >= $v,
            'streak_days'     => (int) optional($user->streak)->longest >= $v,
            'referrals_count' => $this->tree->downlineCount($user->id) >= $v,
            'rank_top'        => ($r = $this->leaderboard->rankOf($user)['rank']) !== null && $r <= $v,
            default           => false,
        };
    }
}
