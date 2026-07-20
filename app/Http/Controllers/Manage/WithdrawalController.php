<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(private WithdrawalService $withdrawals) {}

    public function index(Request $request)
    {
        $query = Withdrawal::query()->with('user');
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        $withdrawals = $query->latest()->paginate(15)->withQueryString();

        $kpis = [
            'pending'      => Withdrawal::where('status', 'pending')->count(),
            'pending_amt'  => (float) Withdrawal::whereIn('status', ['pending', 'approved'])->sum('amount'),
            'paid_amt'     => (float) Withdrawal::where('status', 'paid')->sum('amount'),
        ];

        return view('manage.withdrawals.index', compact('withdrawals', 'kpis'));
    }

    public function approve(Withdrawal $withdrawal, Request $request)
    {
        abort_unless($withdrawal->status === 'pending', 403);
        $this->withdrawals->approve($withdrawal, $request->user(), $request->input('admin_note'));

        return back()->with('ok', 'Withdrawal approved.');
    }

    public function markPaid(Withdrawal $withdrawal, Request $request)
    {
        abort_unless(in_array($withdrawal->status, ['pending', 'approved']), 403);
        $this->withdrawals->markPaid($withdrawal, $request->user());

        return back()->with('ok', 'Withdrawal marked as paid.');
    }

    public function reject(Withdrawal $withdrawal, Request $request)
    {
        abort_unless(in_array($withdrawal->status, ['pending', 'approved']), 403);
        $this->withdrawals->reject($withdrawal, $request->user(), $request->input('admin_note'));

        return back()->with('ok', 'Withdrawal rejected — funds returned to agent wallet.');
    }
}
