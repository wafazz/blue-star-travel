<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request)
    {
        $query = Booking::query()->with('package', 'customer')->where('agent_id', $request->user()->id);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $bookings = $query->latest()->paginate(15)->withQueryString();

        return view('agent.bookings.index', compact('bookings'));
    }

    public function create(Request $request)
    {
        return view('agent.bookings.form', [
            'booking'   => new Booking(['adults' => 1, 'children' => 0, 'infants' => 0, 'type' => 'online']),
            'customers' => Customer::where('agent_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'packages'  => Package::with('pricings', 'dates')->where('status', 'active')->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'package_id'         => ['required', 'exists:packages,id'],
            'package_pricing_id' => ['nullable', 'exists:package_pricings,id'],
            'package_date_id'    => ['nullable', 'exists:package_dates,id'],
            'customer_id'        => ['required', 'exists:customers,id'],
            'type'               => ['required', 'in:' . implode(',', array_keys(Booking::TYPES))],
            'travel_date'        => ['nullable', 'date'],
            'adults'             => ['required', 'integer', 'min:1'],
            'children'           => ['required', 'integer', 'min:0'],
            'infants'            => ['required', 'integer', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'coupon_code'        => ['nullable', 'string', 'max:50'],
            'pax'                => ['nullable', 'array'],
        ]);
        $data['agent_id'] = $request->user()->id;

        // agent may only book their own customers
        abort_unless(Customer::where('id', $data['customer_id'])->where('agent_id', $request->user()->id)->exists(), 403);

        $booking = $this->bookings->create($data, $request->user(), $request->input('pax', []));

        return redirect()->route('agent.bookings.show', $booking)->with('ok', "Booking {$booking->booking_no} submitted.");
    }

    public function show(Booking $booking, Request $request)
    {
        abort_unless($booking->agent_id === $request->user()->id, 403);
        $booking->load('package', 'customer', 'provider', 'packageDate', 'pax', 'timeline.user', 'documents', 'payments');

        return view('agent.bookings.show', compact('booking'));
    }

    public function uploadPayment(Booking $booking, Request $request)
    {
        abort_unless($booking->agent_id === $request->user()->id, 403);

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'method'    => ['required', 'in:' . implode(',', array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'slip'      => ['required', 'image', 'max:4096'],
        ]);
        $data['type'] = $booking->paid_amount > 0 ? 'balance' : 'full';
        $data['slip_path'] = $request->file('slip')->store('payment-slips', 'local');

        $this->bookings->recordPayment($booking, $data, $request->user());

        return back()->with('ok', 'Payment slip uploaded — pending verification.');
    }
}
