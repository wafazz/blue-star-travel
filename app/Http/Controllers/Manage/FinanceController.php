<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function dashboard()
    {
        $verified = Payment::where('status', 'verified');

        $revenue        = (float) (clone $verified)->sum('amount');
        $monthRevenue   = (float) Payment::where('status', 'verified')->whereMonth('verified_at', now()->month)->whereYear('verified_at', now()->year)->sum('amount');
        $refundedTotal  = (float) Refund::where('status', 'processed')->sum('amount');
        $pendingPayAmt  = (float) Payment::where('status', 'pending')->sum('amount');

        // Outstanding balance across live bookings.
        $outstanding = (float) Booking::whereNotIn('status', ['cancelled', 'rejected', 'refunded', 'draft'])
            ->get()->sum(fn ($b) => max(0, $b->balance()));

        // 6-month revenue trend (verified payments).
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->copy()->subMonths($i);
            $sum = (float) Payment::where('status', 'verified')
                ->whereMonth('verified_at', $month->month)
                ->whereYear('verified_at', $month->year)
                ->sum('amount');
            $trend[] = ['label' => $month->format('M'), 'value' => $sum];
        }
        $trendMax = max(1, ...array_map(fn ($t) => $t['value'], $trend));

        $recent = Payment::with('booking.customer')->where('status', 'verified')->latest('verified_at')->limit(8)->get();

        $bookingStatus = Booking::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('manage.finance.dashboard', compact(
            'revenue', 'monthRevenue', 'refundedTotal', 'pendingPayAmt', 'outstanding',
            'trend', 'trendMax', 'recent', 'bookingStatus'
        ));
    }

    public function refunds(Request $request)
    {
        $query = Refund::query()->with('booking.customer', 'requester');
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        $refunds = $query->latest()->paginate(15)->withQueryString();

        $kpis = [
            'pending'   => Refund::where('status', 'pending')->count(),
            'processed' => (float) Refund::where('status', 'processed')->sum('amount'),
        ];

        return view('manage.finance.refunds', compact('refunds', 'kpis'));
    }

    public function requestRefund(Booking $booking, Request $request)
    {
        $data = $request->validate([
            'amount'     => ['required', 'numeric', 'min:0.01', 'max:' . max(0.01, (float) $booking->paid_amount)],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'method'     => ['required', 'in:' . implode(',', array_keys(Refund::METHODS))],
            'reason'     => ['nullable', 'string', 'max:500'],
        ]);

        $this->refunds->request($booking, $data, $request->user());

        return back()->with('ok', 'Refund request created (pending approval).');
    }

    public function approveRefund(Refund $refund, Request $request)
    {
        abort_unless($refund->status === 'pending', 403);
        $this->refunds->approve($refund, $request->user(), $request->input('admin_note'));

        return back()->with('ok', 'Refund approved.');
    }

    public function rejectRefund(Refund $refund, Request $request)
    {
        abort_unless($refund->status === 'pending', 403);
        $this->refunds->reject($refund, $request->user(), $request->input('admin_note'));

        return back()->with('ok', 'Refund rejected.');
    }

    public function processRefund(Refund $refund, Request $request)
    {
        abort_unless($refund->status === 'approved', 403);
        $this->refunds->process($refund, $request->user());

        return back()->with('ok', 'Refund processed.');
    }
}
