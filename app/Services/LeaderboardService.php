<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    /**
     * Monthly agent ranking by verified sales (sum of paid_amount on their bookings
     * whose payments cleared in the given period). Returns a collection of
     * ['user_id','name','agent_tier','sales'] ordered desc.
     */
    public function monthly(?string $period = null)
    {
        $period = $period ?: now()->format('Y-m');
        [$year, $month] = explode('-', $period);

        $rows = Booking::query()
            ->selectRaw('agent_id, SUM(paid_amount) as sales, COUNT(*) as bookings')
            ->whereNotNull('agent_id')
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy('agent_id')
            ->orderByDesc('sales')
            ->get();

        $userIds = $rows->pluck('agent_id');
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'agent_tier', 'agent_code'])->keyBy('id');

        return $rows->map(fn ($r, $i) => (object) [
            'rank'       => $i + 1,
            'user_id'    => $r->agent_id,
            'name'       => $users[$r->agent_id]->name ?? 'Agent',
            'agent_tier' => $users[$r->agent_id]->agent_tier ?? 'silver',
            'sales'      => (float) $r->sales,
            'bookings'   => (int) $r->bookings,
        ])->values();
    }

    /** 1-based rank of an agent this month, plus total agents ranked. */
    public function rankOf(User $user, ?string $period = null): array
    {
        $board = $this->monthly($period);
        $total = max($board->count(), User::where('role', 'agent')->count());
        $me = $board->firstWhere('user_id', $user->id);

        return ['rank' => $me->rank ?? null, 'total' => $total, 'sales' => $me->sales ?? 0.0];
    }
}
