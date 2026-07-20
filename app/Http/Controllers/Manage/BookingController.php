<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request)
    {
        $query = Booking::query()->with('package', 'customer', 'agent');

        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($w) use ($search) {
                $w->where('booking_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $bookings = $query->latest()->paginate(12)->withQueryString();

        $counts = [
            'pending_verification'          => Booking::where('status', 'pending_verification')->count(),
            'waiting_provider_confirmation' => Booking::where('status', 'waiting_provider_confirmation')->count(),
            'confirmed'                     => Booking::where('status', 'confirmed')->count(),
        ];

        return view('manage.bookings.index', compact('bookings', 'counts'));
    }

    public function create()
    {
        return view('manage.bookings.form', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $booking = $this->bookings->create($data, $request->user(), $request->input('pax', []));

        return redirect()->route('manage.bookings.show', $booking)->with('ok', "Booking {$booking->booking_no} created.");
    }

    public function show(Booking $booking)
    {
        $booking->load('package', 'customer', 'agent', 'provider', 'packageDate', 'pricing', 'pax', 'timeline.user', 'documents', 'payments', 'refunds');

        return view('manage.bookings.show', compact('booking'));
    }

    public function submitToProvider(Booking $booking, Request $request)
    {
        abort_unless($booking->status === 'pending_verification', 403);
        $this->bookings->submitToProvider($booking, $request->user());

        return back()->with('ok', 'Booking verified and sent to provider.');
    }

    public function confirm(Booking $booking, Request $request)
    {
        abort_unless(in_array($booking->status, ['pending_verification', 'waiting_provider_confirmation']), 403);
        $this->bookings->confirm($booking, $request->user());

        return back()->with('ok', 'Booking confirmed. Invoice & travel voucher generated.');
    }

    public function reject(Booking $booking, Request $request)
    {
        $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);
        $this->bookings->reject($booking, $request->user(), $request->input('rejection_reason'));

        return back()->with('ok', 'Booking rejected.');
    }

    public function complete(Booking $booking, Request $request)
    {
        abort_unless($booking->status === 'confirmed', 403);
        $this->bookings->complete($booking, $request->user());

        return back()->with('ok', 'Booking marked as completed.');
    }

    public function cancel(Booking $booking, Request $request)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->bookings->cancel($booking, $request->user(), $request->input('reason'));

        return back()->with('ok', 'Booking cancelled.');
    }

    public function addNote(Booking $booking, Request $request)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $this->bookings->log($booking, $request->user(), 'Note added', $data['note']);

        return back()->with('ok', 'Note added.');
    }

    public function recordPayment(Booking $booking, Request $request)
    {
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'method'    => ['required', 'in:' . implode(',', array_keys(Payment::METHODS))],
            'type'      => ['required', 'in:deposit,partial,balance,full'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note'      => ['nullable', 'string', 'max:500'],
            'slip'      => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('slip')) {
            $data['slip_path'] = $request->file('slip')->store('payment-slips', 'local');
        }

        $this->bookings->recordPayment($booking, $data, $request->user());

        return back()->with('ok', 'Payment recorded (pending verification).');
    }

    public function verifyPayment(Payment $payment, Request $request)
    {
        $this->bookings->verifyPayment($payment, $request->user());

        return back()->with('ok', 'Payment verified.');
    }

    public function rejectPayment(Payment $payment, Request $request)
    {
        $this->bookings->rejectPayment($payment, $request->user());

        return back()->with('ok', 'Payment rejected.');
    }

    private function formData(): array
    {
        return [
            'booking'   => new Booking(['adults' => 1, 'children' => 0, 'infants' => 0, 'type' => 'manual']),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'packages'  => Package::with('pricings', 'dates')->where('status', 'active')->orderBy('title')->get(),
            'agents'    => User::where('role', 'agent')->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'package_id'         => ['required', 'exists:packages,id'],
            'package_pricing_id' => ['nullable', 'exists:package_pricings,id'],
            'package_date_id'    => ['nullable', 'exists:package_dates,id'],
            'customer_id'        => ['required', 'exists:customers,id'],
            'agent_id'           => ['nullable', 'exists:users,id'],
            'type'               => ['required', 'in:' . implode(',', array_keys(Booking::TYPES))],
            'travel_date'        => ['nullable', 'date'],
            'adults'             => ['required', 'integer', 'min:1'],
            'children'           => ['required', 'integer', 'min:0'],
            'infants'            => ['required', 'integer', 'min:0'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'coupon_code'        => ['nullable', 'string', 'max:50'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'pax'                => ['nullable', 'array'],
        ]);
    }
}
