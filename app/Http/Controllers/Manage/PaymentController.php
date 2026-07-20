<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\BookingService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request)
    {
        $query = Payment::query()->with('booking.customer', 'booking.package');

        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($w) use ($search) {
                $w->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('booking', fn ($b) => $b->where('booking_no', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($method = $request->get('method')) {
            $query->where('method', $method);
        }

        $payments = $query->latest()->paginate(15)->withQueryString();

        $kpis = [
            'pending'         => Payment::where('status', 'pending')->count(),
            'pending_amount'  => (float) Payment::where('status', 'pending')->sum('amount'),
            'verified_amount' => (float) Payment::where('status', 'verified')->sum('amount'),
            'today_amount'    => (float) Payment::where('status', 'verified')->whereDate('verified_at', today())->sum('amount'),
        ];

        return view('manage.payments.index', compact('payments', 'kpis'));
    }

    public function verify(Payment $payment, Request $request)
    {
        abort_unless($payment->status === 'pending', 403);
        $this->bookings->verifyPayment($payment, $request->user());

        return back()->with('ok', 'Payment verified.');
    }

    public function reject(Payment $payment, Request $request)
    {
        abort_unless($payment->status === 'pending', 403);
        $this->bookings->rejectPayment($payment, $request->user());

        return back()->with('ok', 'Payment rejected.');
    }
}
