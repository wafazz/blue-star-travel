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
            'booking'   => new Booking(['adults' => 1, 'children' => 0, 'seniors' => 0, 'infants' => 0, 'type' => 'online']),
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
            // Either pick one of the agent's customers, or register a new one inline.
            'customer_id'        => ['required_without:new_customer_name', 'nullable', 'exists:customers,id'],
            'new_customer_name'  => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'new_customer_phone' => ['required_with:new_customer_name', 'nullable', 'string', 'max:50'],
            'new_customer_email' => ['nullable', 'email', 'max:255'],
            'type'               => ['required', 'in:' . implode(',', array_keys(Booking::TYPES))],
            'travel_date'        => ['nullable', 'date'],
            // Pax now live on the room lines; these stay for the customer portal + API callers.
            'adults'             => ['nullable', 'integer', 'min:0'],
            'children'           => ['nullable', 'integer', 'min:0'],
            'seniors'            => ['nullable', 'integer', 'min:0'],
            'infants'            => ['nullable', 'integer', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'coupon_code'        => ['nullable', 'string', 'max:50'],
            'pax'                => ['nullable', 'array'],
            // One line per room type; each carries its own pax split.
            'rooms'                        => ['nullable', 'array'],
            'rooms.*.package_pricing_id'   => ['required', 'exists:package_pricings,id'],
            'rooms.*.adults'               => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.children'             => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.seniors'              => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.infants'              => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);
        $data['agent_id'] = $request->user()->id;

        if (empty($data['customer_id'])) {
            $data['customer_id'] = Customer::create([
                'agent_id' => $request->user()->id,
                'name'     => $data['new_customer_name'],
                'phone'    => $data['new_customer_phone'],
                'email'    => $data['new_customer_email'] ?? null,
                'status'   => 'active',
            ])->id;
        } else {
            // agent may only book their own customers
            abort_unless(Customer::where('id', $data['customer_id'])->where('agent_id', $request->user()->id)->exists(), 403);
        }

        $this->bookings->assertDateSelection(Package::findOrFail($data['package_id']), $data);

        $booking = $this->bookings->create($data, $request->user(), $request->input('pax', []));

        return redirect()->route('agent.bookings.show', $booking)->with('ok', "Booking {$booking->booking_no} submitted.");
    }

    public function show(Booking $booking, Request $request)
    {
        abort_unless($booking->agent_id === $request->user()->id, 403);
        $booking->load('package', 'customer', 'provider', 'packageDate', 'pax', 'rooms', 'timeline.user', 'documents', 'payments');

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
