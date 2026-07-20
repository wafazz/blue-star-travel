<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionLevel;
use App\Models\Setting;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function __construct(private CommissionService $commissions) {}

    // ---- Ledger ----------------------------------------------------------

    public function index(Request $request)
    {
        $query = Commission::query()->with('booking', 'earner', 'sourceAgent');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($period = $request->get('period')) {
            $query->where('period', $period);
        }

        $commissions = $query->latest()->paginate(20)->withQueryString();

        $kpis = [
            'pending_count'  => Commission::where('status', 'pending')->count(),
            'pending_amount' => (float) Commission::where('status', 'pending')->where('is_orphan', false)->sum('amount'),
            'approved_amount'=> (float) Commission::where('status', 'approved')->sum('amount'),
            'orphan_amount'  => (float) Commission::where('is_orphan', true)->whereIn('status', ['pending', 'approved'])->sum('amount'),
        ];

        $periods = Commission::select('period')->distinct()->orderByDesc('period')->pluck('period');

        return view('manage.commission.index', compact('commissions', 'kpis', 'periods'));
    }

    public function approve(Commission $commission, Request $request)
    {
        $this->commissions->approve($commission, $request->user());

        return back()->with('ok', 'Commission approved and credited to wallet.');
    }

    public function reject(Commission $commission, Request $request)
    {
        $this->commissions->reject($commission, $request->user());

        return back()->with('ok', 'Commission rejected.');
    }

    public function approvePeriod(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'string', 'size:7']]);
        $n = $this->commissions->approvePeriod($data['period'], $request->user());

        return back()->with('ok', "Approved {$n} commission(s) for {$data['period']}.");
    }

    // ---- Level configuration (dynamic depth) -----------------------------

    public function levels()
    {
        $levels = CommissionLevel::orderBy('level')->get();
        $maxDepth = (int) Setting::get('agent_max_depth', 0);

        return view('manage.commission.levels', compact('levels', 'maxDepth'));
    }

    public function storeLevel(Request $request)
    {
        $data = $request->validate([
            'level'   => ['required', 'integer', 'min:1', 'unique:commission_levels,level'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'label'   => ['nullable', 'string', 'max:60'],
        ]);
        $data['active'] = true;
        CommissionLevel::create($data);

        return back()->with('ok', "Level {$data['level']} added.");
    }

    public function updateLevel(CommissionLevel $level, Request $request)
    {
        $data = $request->validate([
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'label'   => ['nullable', 'string', 'max:60'],
            'active'  => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        $level->update($data);

        return back()->with('ok', "Level {$level->level} updated.");
    }

    public function destroyLevel(CommissionLevel $level)
    {
        $level->delete();

        return back()->with('ok', "Level {$level->level} removed.");
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate(['agent_max_depth' => ['required', 'integer', 'min:0', 'max:50']]);
        Setting::put('agent_max_depth', (string) $data['agent_max_depth']);

        return back()->with('ok', 'Recruitment depth cap saved.');
    }
}
