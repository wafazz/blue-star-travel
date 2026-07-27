<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAmendment;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Services\BookingRevisionService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private BookingRevisionService $revisions,
    ) {}

    public function index(Request $request)
    {
        $query = Booking::query()->with('package', 'customer', 'packageDate')->where('agent_id', $request->user()->id);

        // Tabs are agent-facing labels, each covering one or more DB statuses.
        if ($statuses = Booking::statusesForTab((string) $request->input('tab'))) {
            $query->whereIn('status', $statuses);
        }

        // The client's "Search name / phone" box — booking number too, since agents quote it.
        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($w) use ($search) {
                $w->where('booking_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $bookings = $query->latest()->paginate(15)->withQueryString();

        return view('agent.bookings.index', compact('bookings'));
    }

    /**
     * Upcoming trips, grouped by whichever date the agent is sorting on. "Upcoming" is
     * always about the arrival date; the toggle only changes what the list is ordered
     * and grouped by, exactly like the client's reference.
     */
    public function upcoming(Request $request)
    {
        $by = $request->input('by') === 'reservation' ? 'reservation' : 'arrival';

        $query = Booking::query()
            ->with('package', 'customer', 'packageDate')
            ->where('agent_id', $request->user()->id)
            // A cancelled or finished trip is not upcoming.
            ->whereNotIn('status', ['cancelled', 'rejected', 'refunded', 'completed'])
            // An open-dated booking has no departure row, so fall back to travel_date.
            ->where(function ($w) {
                $w->whereHas('packageDate', fn ($d) => $d->whereDate('depart_date', '>=', today()))
                    ->orWhere(fn ($q) => $q->doesntHave('packageDate')->whereDate('travel_date', '>=', today()));
            });

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($w) use ($search) {
                $w->where('booking_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('package', fn ($p) => $p->where('title', 'like', "%{$search}%"));
            });
        }

        $bookings = $by === 'reservation'
            ? $query->orderByDesc('created_at')->get()
            // Arrival order can't be done in SQL alone — it lives on two columns — so the
            // list is sorted in PHP. Fine here: it is one agent's upcoming trips, not a feed.
            : $query->get()->sortBy(fn ($b) => $b->arrivalDate())->values();

        $groups = $bookings->groupBy(fn ($b) => optional(
            $by === 'reservation' ? $b->created_at : $b->arrivalDate()
        )->format('l, j F Y') ?? 'No date yet');

        return view('agent.bookings.upcoming', compact('groups', 'bookings', 'by'));
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
        // "➕ New customer…" posts a sentinel rather than "" so Select2 keeps it in the
        // list. Normalise it away before validation — the rest of the method already
        // treats an empty customer_id as "register the inline one".
        if ($request->input('customer_id') === '__new') {
            $request->merge(['customer_id' => null]);
        }

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
            'pickup_location'    => ['nullable', 'string', 'max:255'],
            'arrival_time'       => ['nullable', 'date_format:H:i'],
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
        $booking->load('package', 'customer', 'provider', 'packageDate', 'pax', 'rooms', 'timeline.user', 'documents', 'payments', 'openRevisionRequest', 'draft', 'amendments.reviewer');

        return view('agent.bookings.show', compact('booking'));
    }

    public function edit(Booking $booking, Request $request)
    {
        $this->assertEditable($booking, $request);

        $booking->load('package', 'customer', 'rooms', 'pax', 'payments', 'openRevisionRequest');

        return view('agent.bookings.edit', [
            'booking'  => $booking,
            'payload'  => $this->revisions->formPayload($booking, $request->user()),
            'draft'    => $this->revisions->draftFor($booking, $request->user()),
            'flagged'  => $booking->openRevisionRequest,
            'packages' => Package::with('pricings', 'dates')->where('status', 'active')->orderBy('title')->get(),
        ]);
    }

    /**
     * "Save as Draft" — stages the edit and nothing else. The live booking, its rooms,
     * its pax, the customer and the payments are deliberately left untouched.
     */
    public function saveDraft(Booking $booking, Request $request)
    {
        $this->assertEditable($booking, $request);

        $payload = $this->draftPayload($booking, $request);
        $this->revisions->stage($booking, $payload, $request->user());

        return redirect()->route('agent.bookings.edit', $booking)->with('ok', 'Draft saved. Nothing sent to admin yet.');
    }

    /** Same validation and staging as saveDraft — only the redirect target differs. */
    public function stageForReview(Booking $booking, Request $request)
    {
        $this->assertEditable($booking, $request);

        $payload = $this->draftPayload($booking, $request);
        $this->revisions->stage($booking, $payload, $request->user());

        return redirect()->route('agent.bookings.review.show', $booking);
    }

    public function review(Booking $booking, Request $request)
    {
        $this->assertEditable($booking, $request);

        $draft = $this->revisions->draftFor($booking, $request->user());
        if (! $draft) {
            return redirect()->route('agent.bookings.edit', $booking)
                ->withErrors(['status' => 'Nothing staged yet — make your changes first.']);
        }

        // Diffed against the CURRENT live record, not the version the edit started from,
        // so a stale draft shows what it would actually change right now.
        $live = $this->revisions->snapshot($booking);
        $priced = $this->revisions->price($draft->payload);
        $forfeit = $this->bookings->forfeiture($booking, $draft->payload);

        return view('agent.bookings.review', [
            'booking'  => $booking,
            'draft'    => $draft,
            'rows'     => $this->revisions->diff($live, $draft->payload),
            'forfeit'  => $forfeit,
            'newTotal' => $newTotal = max(0, $priced['total'] - (float) $booking->discount),
            // The penalty comes out of what was paid, so it lands in the balance, not the total.
            'newBalance' => round($newTotal + (float) $booking->forfeited_amount
                + $forfeit['amount'] - (float) $booking->paid_amount, 2),
        ]);
    }

    /** Step 5 of the client flow — the confirm screen, separate from the diff. */
    public function confirm(Booking $booking, Request $request)
    {
        $this->assertEditable($booking, $request);

        $draft = $this->revisions->draftFor($booking, $request->user());
        if (! $draft) {
            return redirect()->route('agent.bookings.edit', $booking)
                ->withErrors(['status' => 'Nothing staged yet — make your changes first.']);
        }

        return view('agent.bookings.confirm', [
            'booking' => $booking,
            'changes' => count($this->revisions->diff($this->revisions->snapshot($booking), $draft->payload)),
            'forfeit' => $this->bookings->forfeiture($booking, $draft->payload),
        ]);
    }

    public function resubmit(Booking $booking, Request $request)
    {
        $this->assertEditable($booking, $request);

        // Server-side, not just a required checkbox in the markup.
        $request->validate(['confirm' => ['accepted']], [
            'confirm.accepted' => 'Please confirm the information is correct.',
        ]);

        $version = $this->bookings->resubmitRevision($booking, $request->user());

        // Step 6 — the client's success screen, reached by redirect so a refresh is safe.
        return redirect()->route('agent.bookings.submitted', [$booking, 'v' => $version->version]);
    }

    public function submitted(Booking $booking, Request $request)
    {
        abort_unless($booking->agent_id === $request->user()->id, 403);

        return view('agent.bookings.submitted', [
            'booking' => $booking,
            'version' => (int) $request->input('v'),
        ]);
    }

    public function requestAmendment(Booking $booking, Request $request)
    {
        abort_unless($booking->agent_id === $request->user()->id, 403);

        $data = $request->validate([
            'type'                      => ['required', 'in:' . implode(',', array_keys(BookingAmendment::TYPES))],
            'reason'                    => ['required', 'string', 'max:1000'],
            // A date change needs one of the two: a free-typed date OR a scheduled
            // departure. The form offers both, so neither may be required on its own.
            'requested_date' => [
                'nullable', 'date', 'after:today',
                Rule::requiredIf(fn () => $request->input('type') === 'travel_date'
                    && ! $request->filled('requested_package_date_id')),
            ],
            'requested_package_date_id' => ['nullable', 'exists:package_dates,id'],
            'requested_pickup_location' => ['nullable', 'required_if:type,pickup', 'string', 'max:255'],
            'requested_arrival_time'    => ['nullable', 'date_format:H:i'],
        ]);

        $this->bookings->requestAmendment($booking, $request->user(), $data);

        return back()->with('ok', 'Amendment request submitted — HQ will review it.');
    }

    /**
     * The agent cancels; HQ refunds. Cancelling forfeits RM x per remaining pack out of
     * whatever has been paid — the agent is shown that figure and has to type CANCEL,
     * because nothing here is reversible.
     */
    public function cancel(Booking $booking, Request $request)
    {
        abort_unless($booking->agent_id === $request->user()->id, 403);

        if (! $booking->isCancellableByAgent()) {
            return back()->withErrors(['status' => 'This booking can no longer be cancelled.']);
        }

        $request->validate([
            'confirm' => ['required', 'in:CANCEL'],
            'reason'  => ['required', 'string', 'max:1000'],
        ], [
            'confirm.in'       => 'Type CANCEL to confirm — this cannot be undone.',
            'confirm.required' => 'Type CANCEL to confirm — this cannot be undone.',
            'reason.required'  => 'Please say why the customer is cancelling.',
        ]);

        $this->bookings->cancel($booking, $request->user(), $request->input('reason'));
        $booking->refresh();

        $note = $booking->forfeited_amount > 0
            ? 'Booking cancelled. RM ' . number_format($booking->forfeited_amount, 2)
                . ' forfeited (' . $booking->forfeited_packs . ' pack).'
            : 'Booking cancelled.';

        if ($booking->refundableAmount() > 0) {
            $note .= ' HQ will process the RM ' . number_format($booking->refundableAmount(), 2) . ' refund.';
        }

        return redirect()->route('agent.bookings.show', $booking)->with('ok', $note);
    }

    public function discardDraft(Booking $booking, Request $request)
    {
        $this->assertEditable($booking, $request);
        $this->revisions->discardDraft($booking, $request->user());

        return redirect()->route('agent.bookings.show', $booking)->with('ok', 'Draft discarded.');
    }

    /**
     * Serve a receipt staged in a draft. It has no Payment row yet, so the existing
     * payments.slip route cannot reach it — and minting a placeholder Payment would
     * pollute the staff verification queue.
     */
    public function draftSlip(Booking $booking, Request $request)
    {
        abort_unless($booking->agent_id === $request->user()->id, 403);

        $path = $this->revisions->draftFor($booking, $request->user())?->value('payment.slip_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /** Q1: agents may edit in `draft` and `needs_revision` only. */
    private function assertEditable(Booking $booking, Request $request): void
    {
        abort_unless($booking->agent_id === $request->user()->id, 403);
        abort_unless($booking->isEditableByAgent(), 403, 'This booking can no longer be edited.');
    }

    /** Validate the edit form and fold it into the stored payload shape. */
    private function draftPayload(Booking $booking, Request $request): array
    {
        $data = $request->validate([
            'customer_name'      => ['required', 'string', 'max:255'],
            'customer_phone'     => ['required', 'string', 'max:50'],
            'customer_email'     => ['nullable', 'email', 'max:255'],
            'customer_ic'        => ['nullable', 'string', 'max:100'],
            'package_id'         => ['required', 'exists:packages,id'],
            'package_date_id'    => ['nullable', 'exists:package_dates,id'],
            'travel_date'        => ['nullable', 'date'],
            'pickup_location'    => ['nullable', 'string', 'max:255'],
            'arrival_time'       => ['nullable', 'date_format:H:i'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'rooms'                      => ['nullable', 'array'],
            'rooms.*.package_pricing_id' => ['required', 'exists:package_pricings,id'],
            'rooms.*.adults'             => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.children'           => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.seniors'            => ['nullable', 'integer', 'min:0', 'max:99'],
            'rooms.*.infants'            => ['nullable', 'integer', 'min:0', 'max:99'],
            'pax'                   => ['nullable', 'array'],
            'pax.*.key'             => ['nullable', 'string', 'max:20'],
            'pax.*.name'            => ['nullable', 'string', 'max:255'],
            'pax.*.type'            => ['nullable', 'in:adult,child,senior,infant'],
            'pax.*.age'             => ['nullable', 'integer', 'min:0', 'max:120'],
            'pax.*.ic_passport_no'  => ['nullable', 'string', 'max:100'],
            'pax.*.dob'             => ['nullable', 'date'],
            'pax.*.nationality'     => ['nullable', 'string', 'max:100'],
            'payment_amount'    => ['nullable', 'numeric', 'min:0'],
            'payment_method'    => ['nullable', 'in:' . implode(',', array_keys(Payment::METHODS))],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            // Same allow-list as uploadPayment() — §12 M2.
            'slip'              => ['nullable', 'image', 'max:4096'],
        ]);

        $current = $this->revisions->formPayload($booking, $request->user());
        $package = Package::find($data['package_id']);

        // The form blanks travel_date on fixed-date packages, so derive it from the chosen
        // departure here. Without this the diff shows a phantom "Travel Date → —" row for
        // a value apply() is about to put straight back.
        $departure = $package?->dates->firstWhere('id', (int) ($data['package_date_id'] ?? 0));
        $travelDate = optional($departure)->depart_date?->format('Y-m-d') ?? ($data['travel_date'] ?? null);

        // A replaced receipt goes to the private disk immediately; only its path is staged.
        // The superseded file is never deleted — older versions still link to it.
        $slipPath = $request->hasFile('slip')
            ? $request->file('slip')->store('payment-slips', 'local')
            : data_get($current, 'payment.slip_path');

        return [
            'v' => BookingRevisionService::PAYLOAD_VERSION,
            'booking' => [
                'package_id'      => (int) $data['package_id'],
                'package_title'   => $package?->title,
                'package_date_id' => $data['package_date_id'] ?? null,
                'departure_label' => optional($departure)->depart_date?->format('d M Y'),
                'travel_date'     => $travelDate,
                'pickup_location' => $data['pickup_location'] ?? null,
                'arrival_time'    => $data['arrival_time'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ],
            'customer' => [
                'customer_id'    => $booking->customer_id,
                'name'           => $data['customer_name'],
                'phone'          => $data['customer_phone'],
                'email'          => $data['customer_email'] ?? null,
                'ic_passport_no' => $data['customer_ic'] ?? null,
            ],
            'rooms' => collect($data['rooms'] ?? [])->map(fn ($r) => [
                'package_pricing_id' => (int) $r['package_pricing_id'],
                'room_name'          => optional($package?->pricings->firstWhere('id', (int) $r['package_pricing_id']))->tier_name,
                'adults'             => (int) ($r['adults'] ?? 0),
                'children'           => (int) ($r['children'] ?? 0),
                'seniors'            => (int) ($r['seniors'] ?? 0),
                'infants'            => (int) ($r['infants'] ?? 0),
            ])->values()->all(),
            'pax' => collect($data['pax'] ?? [])->filter(fn ($p) => ! empty($p['name']))->map(fn ($p, $i) => [
                'key'            => $p['key'] ?? "new-{$i}",
                'name'           => $p['name'],
                'type'           => $p['type'] ?? 'adult',
                'age'            => isset($p['age']) && $p['age'] !== '' ? (int) $p['age'] : null,
                'ic_passport_no' => $p['ic_passport_no'] ?? null,
                'dob'            => $p['dob'] ?? null,
                'nationality'    => $p['nationality'] ?? null,
                'is_lead'        => (bool) data_get($current, "pax.{$i}.is_lead", $i === 0),
            ])->values()->all(),
            'payment' => [
                'payment_id' => data_get($current, 'payment.payment_id'),
                'amount'     => isset($data['payment_amount']) ? (string) $data['payment_amount'] : null,
                'method'     => $data['payment_method'] ?? null,
                'reference'  => $data['payment_reference'] ?? null,
                'slip_path'  => $slipPath,
            ],
            // Indicative only — re-derived from roomLines() at resubmit (Phase D).
            'money' => data_get($current, 'money', []),
        ];
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
