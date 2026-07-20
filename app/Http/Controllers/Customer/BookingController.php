<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request)
    {
        $customer = $this->customer($request);

        $query = Booking::with('package')->where('customer_id', $customer->id);
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        $bookings = $query->latest()->paginate(15)->withQueryString();

        return view('customer.bookings.index', compact('bookings'));
    }

    public function create(Request $request)
    {
        $this->customer($request);

        $package = Package::with('pricings', 'dates')
            ->where('slug', $request->get('package'))->where('status', 'active')->firstOrFail();

        return view('customer.bookings.create', compact('package'));
    }

    public function store(Request $request)
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'package_id'         => ['required', 'exists:packages,id'],
            'package_pricing_id' => ['nullable', 'exists:package_pricings,id'],
            'package_date_id'    => ['nullable', 'exists:package_dates,id'],
            'travel_date'        => ['nullable', 'date'],
            'adults'             => ['required', 'integer', 'min:1', 'max:20'],
            'children'           => ['required', 'integer', 'min:0', 'max:20'],
            'infants'            => ['required', 'integer', 'min:0', 'max:20'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'coupon_code'        => ['nullable', 'string', 'max:50'],
        ]);

        $data['customer_id'] = $customer->id;
        $data['agent_id']    = $customer->agent_id;
        $data['type']        = 'online';

        $booking = $this->bookings->create($data, $request->user(), [
            ['name' => $customer->name, 'type' => 'adult', 'ic_passport_no' => $customer->ic_passport_no, 'is_lead' => true],
        ]);

        return redirect()->route('customer.bookings.show', $booking)
            ->with('ok', "Booking {$booking->booking_no} submitted — please complete payment.");
    }

    public function show(Booking $booking, Request $request)
    {
        $customer = $this->customer($request);
        abort_unless($booking->customer_id === $customer->id, 403);
        $booking->load('package', 'provider', 'packageDate', 'pax', 'timeline', 'documents', 'payments');

        return view('customer.bookings.show', compact('booking'));
    }

    public function uploadPayment(Booking $booking, Request $request)
    {
        $customer = $this->customer($request);
        abort_unless($booking->customer_id === $customer->id, 403);

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

    private function customer(Request $request)
    {
        $customer = $request->user()->customerProfile;
        abort_unless($customer, 403, 'No customer profile linked to this account.');

        return $customer;
    }
}
