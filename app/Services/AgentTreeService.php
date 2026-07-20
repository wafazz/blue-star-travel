<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closure-table hierarchy for the agent network.
 *  - agent_tree(ancestor_id, descendant_id, depth)
 *  - the ONLY writer of agent_tree is register().
 */
class AgentTreeService
{
    /**
     * Register an agent under an (optional) referrer. Inserts the self row (depth 0)
     * and, if a referrer exists, a row for every ancestor of the referrer + the
     * referrer itself, each at their depth + 1.
     */
    public function register(User $agent, ?User $referrer): void
    {
        DB::transaction(function () use ($agent, $referrer) {
            // idempotent — skip if already placed
            $exists = DB::table('agent_tree')
                ->where('ancestor_id', $agent->id)
                ->where('descendant_id', $agent->id)
                ->exists();
            if ($exists) {
                return;
            }

            $rows = [[
                'ancestor_id'   => $agent->id,
                'descendant_id' => $agent->id,
                'depth'         => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]];

            if ($referrer) {
                $ancestors = DB::table('agent_tree')
                    ->where('descendant_id', $referrer->id)
                    ->get(['ancestor_id', 'depth']);

                foreach ($ancestors as $a) {
                    $rows[] = [
                        'ancestor_id'   => $a->ancestor_id,
                        'descendant_id' => $agent->id,
                        'depth'         => $a->depth + 1,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }

                $agent->update(['referrer_id' => $referrer->id]);
            }

            DB::table('agent_tree')->insert($rows);
        });
    }

    /**
     * Ordered upline (closest first), excluding self. Optionally capped at $limit levels.
     * Returns a collection of ['user_id' => int, 'depth' => int].
     */
    public function uplineChain(int $agentId, ?int $limit = null)
    {
        $q = DB::table('agent_tree')
            ->where('descendant_id', $agentId)
            ->where('depth', '>', 0)
            ->orderBy('depth');

        if ($limit !== null) {
            $q->where('depth', '<=', $limit);
        }

        return $q->get(['ancestor_id as user_id', 'depth']);
    }

    /** Direct + indirect downline count. */
    public function downlineCount(int $agentId): int
    {
        return DB::table('agent_tree')
            ->where('ancestor_id', $agentId)
            ->where('depth', '>', 0)
            ->count();
    }

    public function depth(int $agentId): int
    {
        return (int) DB::table('agent_tree')
            ->where('descendant_id', $agentId)
            ->max('depth');
    }
}
