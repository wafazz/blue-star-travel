<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Redemption;
use App\Services\GamificationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class RedemptionController extends Controller
{
    public function __construct(
        private GamificationService $game,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        $query = Redemption::query()->with('user');
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        $redemptions = $query->latest()->paginate(15)->withQueryString();

        $kpis = [
            'pending'      => Redemption::where('status', 'pending')->count(),
            'points_spent' => (int) Redemption::whereIn('status', ['approved', 'fulfilled'])->sum('points_cost'),
            'cash_value'   => (float) Redemption::whereIn('status', ['approved', 'fulfilled'])->sum('cash_value'),
        ];

        return view('manage.redemptions.index', compact('redemptions', 'kpis'));
    }

    public function approve(Redemption $redemption, Request $request)
    {
        abort_unless($redemption->status === 'pending', 403);
        $redemption->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'admin_note' => $request->input('admin_note')]);

        return back()->with('ok', 'Redemption approved.');
    }

    public function fulfill(Redemption $redemption, Request $request)
    {
        abort_unless(in_array($redemption->status, ['pending', 'approved']), 403);
        $redemption->update(['status' => 'fulfilled', 'approved_by' => $redemption->approved_by ?? $request->user()->id, 'fulfilled_at' => now()]);
        $this->notifications->notify(
            $redemption->user, 'redemption',
            "Reward ready: {$redemption->typeLabel()}",
            "Your {$redemption->redemption_no} redemption has been fulfilled.",
            route('agent.wallet.index'),
        );

        return back()->with('ok', 'Redemption fulfilled.');
    }

    public function reject(Redemption $redemption, Request $request)
    {
        abort_unless(in_array($redemption->status, ['pending', 'approved']), 403);
        $redemption->update(['status' => 'rejected', 'approved_by' => $request->user()->id, 'admin_note' => $request->input('admin_note')]);
        // return the spent points to the agent
        $this->game->awardPoints($redemption->user, (int) $redemption->points_cost, 'redemption_refund', "Refund for rejected {$redemption->redemption_no}");

        return back()->with('ok', 'Redemption rejected — points returned to agent.');
    }
}
