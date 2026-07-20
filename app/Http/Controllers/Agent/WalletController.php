<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Services\AgentTreeService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $wallet,
        private WithdrawalService $withdrawals,
        private AgentTreeService $tree,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $wallet = $this->wallet->walletFor($user);
        $wallet->load('transactions');

        $pendingCommission = (float) Commission::where('earner_id', $user->id)->where('status', 'pending')->sum('amount');
        $withdrawals = $user->withdrawals()->limit(10)->get();

        return view('agent.wallet.index', compact('wallet', 'pendingCommission', 'withdrawals'));
    }

    public function commissions(Request $request)
    {
        $commissions = Commission::with('booking', 'sourceAgent')
            ->where('earner_id', $request->user()->id)
            ->latest()->paginate(20);

        return view('agent.wallet.commissions', compact('commissions'));
    }

    public function withdraw(Request $request)
    {
        $data = $request->validate([
            'amount'            => ['required', 'numeric', 'min:1'],
            'bank_name'         => ['required', 'string', 'max:100'],
            'bank_account_no'   => ['required', 'string', 'max:50'],
            'bank_account_name' => ['required', 'string', 'max:100'],
            'note'              => ['nullable', 'string', 'max:255'],
        ]);

        $this->withdrawals->request($request->user(), $data);

        return back()->with('ok', 'Withdrawal request submitted.');
    }

    public function network(Request $request)
    {
        $user = $request->user();

        // downline as flat list with depth, ordered
        $rows = DB::table('agent_tree')
            ->join('users', 'users.id', '=', 'agent_tree.descendant_id')
            ->where('agent_tree.ancestor_id', $user->id)
            ->where('agent_tree.depth', '>', 0)
            ->orderBy('agent_tree.depth')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.agent_code', 'users.agent_tier', 'agent_tree.depth']);

        $directCount = $rows->where('depth', 1)->count();
        $totalCount = $rows->count();

        return view('agent.wallet.network', compact('rows', 'directCount', 'totalCount'));
    }
}
