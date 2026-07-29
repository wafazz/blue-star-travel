<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAmendment;
use App\Models\BookingRevisionRequest;
use App\Models\BookingVersion;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'needs_revision'                => Booking::where('status', 'needs_revision')->count(),
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
        $this->bookings->assertDateSelection(Package::findOrFail($data['package_id']), $data);
        $booking = $this->bookings->create($data, $request->user(), $request->input('pax', []));

        return redirect()->route('manage.bookings.show', $booking)->with('ok', "Booking {$booking->booking_no} created.");
    }

    public function show(Booking $booking)
    {
        $booking->load([
            'package', 'customer', 'agent', 'provider', 'packageDate', 'pricing', 'pax', 'rooms',
            'timeline.user', 'timeline.version:id,version', 'documents', 'payments', 'refunds',
            'openRevisionRequest.requester', 'amendments.requester', 'amendments.reviewer', 'amendments.packageDate',
            // The history panel lists versions — it must not drag two JSON blobs per row,
            // and the author name must be eager-loaded or it is one query per version.
            'versions' => fn ($q) => $q->select('id', 'booking_id', 'version', 'reason', 'created_by', 'created_at')
                ->with('author:id,name'),
        ]);

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

        // The resort invoice is what proves the resort side is actually booked and priced.
        // The button ships disabled without one, but a disabled button is a courtesy.
        if (! $booking->documents()->where('type', 'resort_invoice')->exists()) {
            return back()->withErrors(['resort_invoice' => 'Upload the resort invoice before confirming this booking.']);
        }

        $this->bookings->confirm($booking, $request->user());

        return back()->with('ok', 'Booking confirmed. Invoice & travel voucher generated.');
    }

    /**
     * Internal paperwork — the file lands on the private disk and is listed on /manage only.
     * See BookingDocument::INTERNAL_TYPES; agents must never see what the resort charges.
     */
    public function uploadResortInvoice(Booking $booking, Request $request)
    {
        $data = $request->validate([
            'resort_invoice' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'note'           => ['nullable', 'string', 'max:255'],
        ], [
            'resort_invoice.required' => 'Attach the resort invoice.',
            'resort_invoice.mimes'    => 'The resort invoice must be a PDF or an image.',
        ]);

        $path = $request->file('resort_invoice')->store("booking-docs/{$booking->id}", 'local');
        $existing = $booking->documents()->where('type', 'resort_invoice')->first();

        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            $existing->update([
                'file_path'   => $path,
                'uploaded_by' => $request->user()->id,
            ]);
        } else {
            $booking->documents()->create([
                'type'        => 'resort_invoice',
                'title'       => 'Resort-Invoice-' . $booking->booking_no,
                'file_path'   => $path,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $this->bookings->log($booking, $request->user(), $existing ? 'Resort invoice replaced' : 'Resort invoice uploaded', $data['note'] ?? null);

        return back()->with('ok', 'Resort invoice saved. Internal only — agents cannot see it.');
    }

    public function version(Booking $booking, BookingVersion $version)
    {
        abort_unless($version->booking_id === $booking->id, 404);
        $version->load('author', 'revisionRequest.requester');

        return view('manage.bookings.version', compact('booking', 'version'));
    }

    public function approveAmendment(Booking $booking, BookingAmendment $amendment, Request $request)
    {
        abort_unless($amendment->booking_id === $booking->id, 404);
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:500']]);

        $this->bookings->approveAmendment($amendment, $request->user(), $data['admin_note'] ?? null);

        return back()->with('ok', 'Amendment approved and applied.');
    }

    public function rejectAmendment(Booking $booking, BookingAmendment $amendment, Request $request)
    {
        abort_unless($amendment->booking_id === $booking->id, 404);
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:500']]);

        $this->bookings->rejectAmendment($amendment, $request->user(), $data['admin_note'] ?? null);

        return back()->with('ok', 'Amendment rejected.');
    }

    public function requestRevision(Booking $booking, Request $request)
    {
        $data = $request->validate([
            'remark'   => ['required', 'string', 'max:1000'],
            'fields'   => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'in:' . implode(',', array_keys(BookingRevisionRequest::FIELDS))],
        ]);

        $this->bookings->requestRevision($booking, $request->user(), $data['remark'], $data['fields']);

        return back()->with('ok', 'Sent back to the agent for revision.');
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
            'booking'   => new Booking(['adults' => 1, 'children' => 0, 'seniors' => 0, 'infants' => 0, 'type' => 'manual']),
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
            'pickup_location'    => ['nullable', 'string', 'max:255'],
            'arrival_time'       => ['nullable', 'date_format:H:i'],
            // Pax now live on the room lines; these stay for the customer portal + API callers.
            'adults'             => ['nullable', 'integer', 'min:0'],
            'children'           => ['nullable', 'integer', 'min:0'],
            'seniors'            => ['nullable', 'integer', 'min:0'],
            'infants'            => ['nullable', 'integer', 'min:0'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'coupon_code'        => ['nullable', 'string', 'max:50'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'pax'                => ['nullable', 'array'],
            // One line per room type; each carries its own pax split.
            'rooms'                        => ['nullable', 'array'],
            'rooms.*.package_pricing_id'   => ['required', 'exists:package_pricings,id'],
            'rooms.*.adults'               => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.children'             => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.seniors'              => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.infants'              => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);
    }
}
