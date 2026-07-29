<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agent rank promotion engine.
 *  - Tiers (low → high): agent → assistant_mentor → mentor.
 *  - Each tier above `agent` needs BOTH: a min number of downlines who hold the rank just below the
 *    target (Agents for Assistant Mentor, Assistant Mentors for Mentor), AND a min number of packs sold.
 *  - An agent is promoted to the HIGHEST tier whose thresholds they meet (can skip a level).
 *  - Promote-only: never auto-demotes (refunds/clawbacks won't strip a rank). Staff can still set tier manually.
 */
class TierService
{
    public function __construct(
        private AgentTreeService $tree,
        private NotificationService $notifications,
    ) {}

    const ORDER = ['agent', 'assistant_mentor', 'mentor'];

    // Downline rank each promotion counts (the tier immediately below the target).
    const REQUIRES_TIER = [
        'assistant_mentor' => 'agent',
        'mentor'           => 'assistant_mentor',
    ];

    const DEFAULTS = [
        'assistant_mentor' => ['min_tier_downlines' => 5, 'min_packs' => 10],
        'mentor'           => ['min_tier_downlines' => 3, 'min_packs' => 30],
    ];

    /** Configured thresholds (settings override the defaults). */
    public function rules(): array
    {
        $raw = Setting::get('tier_rules');
        $saved = $raw ? (is_array($raw) ? $raw : json_decode($raw, true)) : [];
        $rules = [];
        foreach (self::DEFAULTS as $tier => $def) {
            $rules[$tier] = [
                'min_tier_downlines' => (int) ($saved[$tier]['min_tier_downlines'] ?? $def['min_tier_downlines']),
                'min_packs'          => (int) ($saved[$tier]['min_packs'] ?? $def['min_packs']),
            ];
        }

        return $rules;
    }

    public function saveRules(array $input): void
    {
        $rules = [];
        foreach (self::DEFAULTS as $tier => $def) {
            $rules[$tier] = [
                'min_tier_downlines' => max(0, (int) ($input[$tier]['min_tier_downlines'] ?? $def['min_tier_downlines'])),
                'min_packs'          => max(0, (int) ($input[$tier]['min_packs'] ?? $def['min_packs'])),
            ];
        }
        Setting::put('tier_rules', json_encode($rules));
    }

    /**
     * The six-month qualification period covering $on (default now):
     * H1 = Jan–Jun, H2 = Jul–Dec, reset every year.
     */
    public function currentPeriod($on = null): array
    {
        $on   = $on ? Carbon::parse($on) : Carbon::now();
        $year = $on->year;

        if ($on->month <= 6) {
            [$half, $range, $start, $end] = ['H1', 'Jan–Jun', Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 6, 30)->endOfDay()];
        } else {
            [$half, $range, $start, $end] = ['H2', 'Jul–Dec', Carbon::create($year, 7, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay()];
        }

        return ['key' => "{$year}-{$half}", 'label' => "{$year} {$half} ({$range})", 'start' => $start, 'end' => $end];
    }

    /** The completed period immediately before the one covering $on (default: the last finished period). */
    public function previousPeriod($on = null): array
    {
        $current = $this->currentPeriod($on);

        return $this->currentPeriod($current['start']->copy()->subDay());
    }

    /** Packages sold (confirmed/completed) by the agent within a period (default: current period). */
    public function packsSold(User $agent, ?array $period = null): int
    {
        $p = $period ?? $this->currentPeriod();

        return Booking::where('agent_id', $agent->id)
            ->whereIn('status', Booking::SOLD_STATUSES)
            ->whereBetween('created_at', [$p['start'], $p['end']])
            ->count();
    }

    /** Downlines (whole network) whose rank is at least $tier. */
    public function downlineCountAtTierOrAbove(User $agent, string $tier): int
    {
        $minRank = array_search($tier, self::ORDER, true);
        if ($minRank === false) {
            return 0;
        }

        $descendantIds = DB::table('agent_tree')
            ->where('ancestor_id', $agent->id)->where('depth', '>', 0)
            ->pluck('descendant_id');
        if ($descendantIds->isEmpty()) {
            return 0;
        }

        $allowed = array_slice(self::ORDER, $minRank); // this tier + higher

        return User::whereIn('id', $descendantIds)->whereIn('agent_tier', $allowed)->count();
    }

    /** Progress figures toward a candidate target tier. */
    public function progressFor(User $agent, string $targetTier): array
    {
        $required = self::REQUIRES_TIER[$targetTier] ?? 'agent';

        return [
            'required_tier'  => $required,
            'tier_downlines' => $this->downlineCountAtTierOrAbove($agent, $required),
            'packs'          => $this->packsSold($agent),
        ];
    }

    /** Highest tier the agent qualifies for, using packs from $period (default: current period). */
    public function qualifyingTier(User $agent, ?array $period = null): string
    {
        $rules = $this->rules();
        $packs = $this->packsSold($agent, $period);
        $tier  = 'agent';
        foreach (['assistant_mentor', 'mentor'] as $candidate) {
            $downlines = $this->downlineCountAtTierOrAbove($agent, self::REQUIRES_TIER[$candidate]);
            if ($downlines >= $rules[$candidate]['min_tier_downlines'] && $packs >= $rules[$candidate]['min_packs']) {
                $tier = $candidate;
            }
        }

        return $tier;
    }

    /** Promote the agent up if they've earned it. Returns the new tier when promoted, else null. */
    public function evaluate(User $agent, ?User $actor = null): ?string
    {
        if ($agent->role !== 'agent') {
            return null;
        }

        $current = array_search($agent->agent_tier, self::ORDER, true);
        $current = $current === false ? 0 : $current;
        $target  = array_search($this->qualifyingTier($agent), self::ORDER, true);

        if ($target <= $current) {
            return null; // already at or above — never auto-demote
        }

        $newTier = self::ORDER[$target];
        $agent->update(['agent_tier' => $newTier]);

        $this->notifications->notify(
            $agent, 'system',
            '🎉 Rank up! You are now ' . User::tierLabelFor($newTier),
            'Congratulations — your network and sales earned you a promotion to ' . User::tierLabelFor($newTier) . '.',
            route('agent.dashboard'),
        );

        return $newTier;
    }

    /** Re-evaluate every agent (catches downline-driven promotions + rule changes). Returns promoted count. */
    public function recalculateAll(?User $actor = null): int
    {
        $n = 0;
        foreach (User::where('role', 'agent')->get() as $agent) {
            if ($this->evaluate($agent, $actor)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Re-qualify an agent to EXACTLY the tier their $period production earns — up OR down.
     * This is the maintenance check: a rank not re-earned in the period is lost.
     */
    public function requalify(User $agent, array $period, ?User $actor = null): ?string
    {
        if ($agent->role !== 'agent') {
            return null;
        }

        $currentIdx = array_search($agent->agent_tier, self::ORDER, true);
        $currentIdx = $currentIdx === false ? 0 : $currentIdx;
        $newTier    = $this->qualifyingTier($agent, $period);
        $newIdx     = array_search($newTier, self::ORDER, true);

        if ($newIdx === $currentIdx) {
            return null; // held their rank
        }

        $agent->update(['agent_tier' => $newTier]);

        if ($newIdx > $currentIdx) {
            $this->notifications->notify(
                $agent, 'system',
                '🎉 Rank up! You are now ' . User::tierLabelFor($newTier),
                'Your production in ' . $period['label'] . ' earned you a promotion to ' . User::tierLabelFor($newTier) . '.',
                route('agent.dashboard'),
            );
        } else {
            $this->notifications->notify(
                $agent, 'system',
                'Rank review: moved to ' . User::tierLabelFor($newTier),
                'You did not meet the ' . User::tierLabelFor(self::ORDER[$currentIdx]) . ' targets for ' . $period['label'] . ', so your rank is now ' . User::tierLabelFor($newTier) . '. Re-qualify this period to move back up.',
                route('agent.dashboard'),
            );
        }

        return $newTier;
    }

    /** Re-qualify every agent against $period (up or down). Returns ['promoted'=>n, 'demoted'=>n]. */
    public function requalifyAll(array $period, ?User $actor = null): array
    {
        $res = ['promoted' => 0, 'demoted' => 0];
        foreach (User::where('role', 'agent')->get() as $agent) {
            $before = array_search($agent->agent_tier, self::ORDER, true) ?: 0;
            $new = $this->requalify($agent, $period, $actor);
            if ($new === null) {
                continue;
            }
            $after = array_search($new, self::ORDER, true) ?: 0;
            $res[$after > $before ? 'promoted' : 'demoted']++;
        }

        return $res;
    }

    /**
     * If the qualification period has rolled over since the last run, re-qualify everyone against the
     * period that just ended (applying period-based demotions). Idempotent — acts once per period.
     * Returns ['ran'=>bool, 'promoted'=>int, 'demoted'=>int, 'period'=>label|null].
     */
    public function processPeriodRollover(?User $actor = null): array
    {
        $processed = Setting::get('tier_period_processed');
        $current   = $this->currentPeriod()['key'];

        if ($processed === $current) {
            return ['ran' => false, 'promoted' => 0, 'demoted' => 0, 'period' => null];
        }

        $result = ['ran' => true, 'promoted' => 0, 'demoted' => 0, 'period' => null];

        if ($processed !== null) { // skip the very first run — no prior period to judge against
            $prev = $this->previousPeriod();
            $counts = $this->requalifyAll($prev, $actor);
            $result['promoted'] = $counts['promoted'];
            $result['demoted']  = $counts['demoted'];
            $result['period']   = $prev['label'];
        }

        Setting::put('tier_period_processed', $current);

        return $result;
    }
}
