<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\User;
use App\Services\AgentTreeService;
use App\Services\TierService;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(private AgentTreeService $tree, private TierService $tiers) {}

    const TIERS = User::TIERS;
    const STATUSES = ['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'];

    public function index(Request $request)
    {
        $query = User::where('role', 'agent')
            ->with('wallet', 'referrer')
            ->withCount('referrals as direct_downlines')
            ->withSum(['agentBookings as sales_total' => fn ($q) => $q->whereIn('status', ['confirmed', 'completed'])], 'total_amount');

        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('agent_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        if ($tier = $request->get('tier')) {
            $query->where('agent_tier', $tier);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $agents = $query->orderBy('agent_code')->paginate(15)->withQueryString();

        $kpis = [
            'total'         => User::where('role', 'agent')->count(),
            'active'        => User::where('role', 'agent')->where('status', 'active')->count(),
            'wallet_total'  => (float) \App\Models\Wallet::whereHas('user', fn ($q) => $q->where('role', 'agent'))->sum('balance'),
            'pending_comm'  => (float) Commission::where('status', 'pending')->where('is_orphan', false)->where('is_hq', false)->sum('amount'),
        ];

        $tierRules = $this->tiers->rules();
        $period    = $this->tiers->currentPeriod();

        return view('manage.agents.index', compact('agents', 'kpis', 'tierRules', 'period'));
    }

    public function saveTierRules(Request $request)
    {
        $this->tiers->saveRules($request->input('rules', []));

        return back()->with('ok', 'Tier promotion rules saved.');
    }

    public function recalculate(Request $request)
    {
        $roll = $this->tiers->processPeriodRollover($request->user()); // applies period-based demotions if a period rolled over
        $promoted = $this->tiers->recalculateAll($request->user());

        $msg = "Recalculated — {$promoted} promoted";
        if ($roll['ran'] && ($roll['promoted'] || $roll['demoted'])) {
            $msg .= "; period {$roll['period']} maintenance: {$roll['demoted']} demoted, {$roll['promoted']} re-qualified up";
        }
        $msg .= '.';

        return back()->with('ok', $msg);
    }

    public function show(User $agent)
    {
        abort_unless($agent->role === 'agent', 404);

        $agent->load('wallet', 'referrer', 'referrals.wallet');

        $upline    = $this->tree->uplineChain($agent->id);
        $network   = $this->tree->downlineCount($agent->id);
        $bookings  = $agent->agentBookings()->with('customer', 'package')->latest()->limit(10)->get();
        $salesTotal = (float) $agent->agentBookings()->whereIn('status', ['confirmed', 'completed'])->sum('total_amount');
        $commissions = Commission::where('earner_id', $agent->id)->with('booking')->latest()->limit(10)->get();
        $commissionEarned = (float) Commission::where('earner_id', $agent->id)->whereIn('status', ['approved', 'paid'])->sum('amount');

        $tierIndex = array_search($agent->agent_tier, TierService::ORDER, true);
        $tierIndex = $tierIndex === false ? 0 : $tierIndex;
        $nextTier  = TierService::ORDER[$tierIndex + 1] ?? null;
        $tierRule  = $nextTier ? $this->tiers->rules()[$nextTier] : null;
        $tierProgress = $nextTier ? $this->tiers->progressFor($agent, $nextTier) : null;
        $period    = $this->tiers->currentPeriod();

        return view('manage.agents.show', compact(
            'agent', 'upline', 'network', 'bookings', 'salesTotal', 'commissions', 'commissionEarned',
            'nextTier', 'tierRule', 'tierProgress', 'period'
        ));
    }

    public function update(Request $request, User $agent)
    {
        abort_unless($agent->role === 'agent', 404);

        $data = $request->validate([
            'agent_tier' => ['required', 'in:' . implode(',', array_keys(self::TIERS))],
            'status'     => ['required', 'in:' . implode(',', array_keys(self::STATUSES))],
        ]);
        $agent->update($data);

        return back()->with('ok', "Agent {$agent->agent_code} updated.");
    }
}
