<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\BookingRevisionRequest;
use App\Models\BookingVersion;
use App\Models\Package;
use App\Models\PackageDate;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Snapshot / stage / compare for the agent revision loop. BookingService owns state,
 * money and notifications; this owns the payload shape and the draft that is not yet live.
 */
class BookingRevisionService
{
    /** Payload schema version — lets a future key rename branch instead of rewriting history. */
    const PAYLOAD_VERSION = 1;

    /** Per-passenger diff fields. `age` is the client's "Child Age" — see the mockup. */
    const PAX_FIELDS = [
        'name'           => 'Name',
        'type'           => 'Type',
        'age'            => 'Child Age',
        'dob'            => 'Date of Birth',
        'ic_passport_no' => 'IC / Passport',
        'nationality'    => 'Nationality',
    ];

    /** Scalar diff fields, in the order the review screen renders them. */
    const SCALARS = [
        'customer.name'           => 'customer.name',
        'customer.phone'          => 'customer.phone',
        'customer.email'          => 'customer.email',
        'customer.ic_passport_no' => 'customer.ic_passport_no',
        'booking.package_id'      => 'booking.package_title',
        'booking.package_date_id' => 'booking.departure_label',
        'booking.travel_date'     => 'booking.travel_date',
        'booking.pickup_location' => 'booking.pickup_location',
        'booking.arrival_time'    => 'booking.arrival_time',
        'payment.amount'          => 'payment.amount',
        'payment.method'          => 'payment.method',
        'booking.notes'           => 'booking.notes',
    ];

    /**
     * The live booking as a normalised payload. Display labels are stored beside every
     * foreign key on purpose: the diff renderer must work from JSON alone, with no lookups.
     */
    public function snapshot(Booking $booking): array
    {
        $booking->loadMissing('package', 'packageDate', 'customer', 'rooms', 'pax', 'payments');
        $payment = $booking->payments->sortByDesc('id')->first();

        return [
            'v' => self::PAYLOAD_VERSION,
            'booking' => [
                'package_id'      => $booking->package_id,
                'package_title'   => $booking->package?->title,
                'package_date_id' => $booking->package_date_id,
                'departure_label' => $booking->packageDate?->depart_date?->format('d M Y'),
                'travel_date'     => optional($booking->travel_date)->format('Y-m-d'),
                'pickup_location' => $booking->pickup_location,
                'arrival_time'    => $booking->arrival_time ? substr($booking->arrival_time, 0, 5) : null,
                'notes'           => $booking->notes,
            ],
            'customer' => [
                'customer_id'    => $booking->customer_id,
                'name'           => $booking->customer?->name,
                'phone'          => $booking->customer?->phone,
                'email'          => $booking->customer?->email,
                'ic_passport_no' => $booking->customer?->ic_passport_no,
            ],
            'rooms' => $booking->rooms->map(fn ($r) => [
                'package_pricing_id' => $r->package_pricing_id,
                'room_name'          => $r->room_name,
                'adults'             => (int) $r->adults,
                'children'           => (int) $r->children,
                'seniors'            => (int) $r->seniors,
                'infants'            => (int) $r->infants,
            ])->values()->all(),
            // Keyed by the persisted id so a row deleted from the middle doesn't shift
            // every later passenger and produce a wall of phantom diffs.
            'pax' => $booking->pax->map(fn ($p) => [
                'key'            => (string) $p->id,
                'name'           => $p->name,
                'type'           => $p->type,
                'age'            => $p->age,
                'ic_passport_no' => $p->ic_passport_no,
                'dob'            => optional($p->dob)->format('Y-m-d'),
                'nationality'    => $p->nationality,
                'is_lead'        => (bool) $p->is_lead,
            ])->values()->all(),
            'payment' => [
                'payment_id' => $payment?->id,
                // Strings: json_encode turns 500.00 into 500 and drags float artefacts in.
                'amount'     => $payment ? (string) $payment->amount : null,
                'method'     => $payment?->method,
                'reference'  => $payment?->reference,
                'slip_path'  => $payment?->slip_path,
            ],
            'money' => [
                'subtotal'     => (string) $booking->subtotal,
                'discount'     => (string) $booking->discount,
                'total_amount' => (string) $booking->total_amount,
            ],
        ];
    }

    /**
     * Save the agent's work in progress. Writes ONE row in booking_drafts and nothing
     * else — bookings, booking_rooms, booking_pax, customers and payments are untouched.
     */
    public function stage(Booking $booking, array $payload, ?User $actor): BookingDraft
    {
        return DB::transaction(function () use ($booking, $payload, $actor) {
            $draft = BookingDraft::firstOrNew([
                'booking_id' => $booking->id,
                'user_id'    => $actor?->id,
            ]);

            // Recorded once, when editing begins, so a later staff change can be spotted.
            $draft->base_version ??= $booking->versions()->max('version');
            $draft->revision_request_id = $booking->openRevisionRequest?->id;
            $draft->payload = $payload;
            $draft->save();

            return $draft;
        });
    }

    /** The payload an edit form should show: the staged draft if any, else the live record. */
    public function formPayload(Booking $booking, ?User $actor): array
    {
        $draft = $this->draftFor($booking, $actor);

        return $draft ? $draft->payload : $this->snapshot($booking);
    }

    /** The last applied snapshot, or the live record when the booking has no history yet. */
    public function latestVersionPayload(Booking $booking): array
    {
        return $booking->versions()->first()?->payload ?? $this->snapshot($booking);
    }

    public function draftFor(Booking $booking, ?User $actor): ?BookingDraft
    {
        return $booking->drafts()->where('user_id', $actor?->id)->first();
    }

    public function discardDraft(Booking $booking, ?User $actor): void
    {
        $this->draftFor($booking, $actor)?->delete();
    }

    /**
     * Write the next immutable version row. Called for the `initial` snapshot the first
     * time a booking is sent back, and again on every resubmit.
     */
    public function snapshotVersion(
        Booking $booking,
        ?User $actor,
        string $reason = 'revision',
        ?array $payload = null,
        ?array $changes = null,
        ?int $revisionRequestId = null
    ): BookingVersion {
        $next = (int) $booking->versions()->max('version') + 1;

        return $booking->versions()->create([
            'revision_request_id' => $revisionRequestId,
            'created_by'          => $actor?->id,
            'version'             => $next,
            'reason'              => $reason,
            'payload'             => $payload ?? $this->snapshot($booking),
            'changes'             => $changes,
        ]);
    }

    /**
     * Re-derive money from a staged payload through BookingService::roomLines(), so a
     * resubmission prices exactly like a new booking would today. Never trust
     * payload['money'] — a draft saved in July must not lock in July's rates.
     */
    public function price(array $payload): array
    {
        $package = Package::with('pricings')->find(data_get($payload, 'booking.package_id'));
        if (! $package) {
            return ['lines' => [], 'subtotal' => 0.0, 'total' => 0.0];
        }

        $lines = app(BookingService::class)->roomLines(
            $package,
            ['rooms' => data_get($payload, 'rooms', [])],
            $package->defaultPricing()
        );
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);

        return ['lines' => $lines, 'subtotal' => $subtotal, 'total' => $subtotal];
    }

    /**
     * Before/after rows for the review screen and the frozen `changes` column. Ordered by
     * the field registry so the diff always reads Customer → Travel → Pickup → Payment → Note.
     */
    public function diff(array $before, array $after): array
    {
        $rows = [];

        foreach (self::SCALARS as $key => $path) {
            $from = data_get($before, $path);
            $to   = data_get($after, $path);
            if ((string) $from !== (string) $to) {
                $rows[] = $this->row($key, $from, $to);
            }
        }

        // Collections diff as a rendered one-line summary — element-wise comparison turns
        // a reordered room table into a wall of false positives.
        $roomsBefore = $this->summariseRooms(data_get($before, 'rooms', []));
        $roomsAfter  = $this->summariseRooms(data_get($after, 'rooms', []));
        if ($roomsBefore !== $roomsAfter) {
            $rows[] = $this->row('rooms', $roomsBefore, $roomsAfter);
        }

        $rows = array_merge($rows, $this->diffPax(data_get($before, 'pax', []), data_get($after, 'pax', [])));

        // Never diffed by content — the path changing is the whole signal.
        if (data_get($before, 'payment.slip_path') !== data_get($after, 'payment.slip_path')) {
            $rows[] = $this->row('payment.slip', 'Previous receipt', 'New receipt uploaded') + ['kind' => 'file'];
        }

        return $rows;
    }

    /** Matched by stable key so a deleted passenger doesn't shift every later one. */
    private function diffPax(array $before, array $after): array
    {
        $byKey = fn ($rows) => collect($rows)->keyBy(fn ($p) => $p['key'] ?? '')->all();
        $b = $byKey($before);
        $a = $byKey($after);
        $rows = [];

        foreach ($a as $key => $row) {
            if (! isset($b[$key])) {
                $rows[] = $this->row('pax', null, $this->paxLabel($row)) + ['change' => 'added'];
                continue;
            }
            foreach (self::PAX_FIELDS as $f => $label) {
                if ((string) ($b[$key][$f] ?? '') !== (string) ($row[$f] ?? '')) {
                    // Age reads as "4 years → 6 years", which is how the client asks for it.
                    $fmt = fn ($v) => $f === 'age' ? ($v === null || $v === '' ? null : $v . ' years') : $v;
                    $rows[] = [
                        'key'    => 'pax',
                        'group'  => 'Travel',
                        'label'  => trim(($row['name'] ?? 'Passenger') . ' — ' . $label),
                        'before' => $fmt($b[$key][$f] ?? null),
                        'after'  => $fmt($row[$f] ?? null),
                        'change' => 'modified',
                    ];
                }
            }
        }

        foreach ($b as $key => $row) {
            if (! isset($a[$key])) {
                $rows[] = $this->row('pax', $this->paxLabel($row), null) + ['change' => 'removed'];
            }
        }

        return $rows;
    }

    private function paxLabel(array $row): string
    {
        return trim(($row['name'] ?? '—') . ' (' . ucfirst($row['type'] ?? 'adult') . ')');
    }

    private function summariseRooms(array $rooms): string
    {
        if (! $rooms) {
            return '—';
        }

        return collect($rooms)->map(function ($r) {
            $parts = array_filter([
                ($r['adults'] ?? 0) ? $r['adults'] . 'A' : null,
                ($r['children'] ?? 0) ? $r['children'] . 'C' : null,
                ($r['seniors'] ?? 0) ? $r['seniors'] . 'S' : null,
                ($r['infants'] ?? 0) ? $r['infants'] . 'I' : null,
            ]);

            return ($r['room_name'] ?? 'Room') . ' (' . (implode(' ', $parts) ?: 'empty') . ')';
        })->implode(' + ');
    }

    private function row(string $key, $before, $after): array
    {
        $meta = BookingRevisionRequest::FIELDS[$key] ?? ['label' => $key, 'group' => 'Other'];

        return [
            'key'    => $key,
            'group'  => $meta['group'],
            'label'  => $meta['label'],
            'before' => $before,
            'after'  => $after,
            'change' => 'modified',
        ];
    }

    /**
     * Write a staged payload onto the live records. Callers must already hold the booking
     * lock and be inside a transaction — see BookingService::resubmitRevision().
     */
    public function apply(Booking $booking, array $payload, ?User $actor): void
    {
        $priced = $this->price($payload);

        // A departure fixes the travel date — never let the two disagree. The edit form
        // clears travel_date for fixed-date packages, so without this a resubmitted
        // booking lands with a departure but a blank date (invisible in reports/exports).
        $travelDate = data_get($payload, 'booking.travel_date');
        if ($dateId = data_get($payload, 'booking.package_date_id')) {
            $travelDate = optional(PackageDate::find($dateId))->depart_date ?? $travelDate;
        }

        // Customer keys are written ONLY when they actually changed — the row is shared
        // with that customer's other bookings and is baked into issued invoices.
        if ($customer = $booking->customer) {
            $changed = array_filter([
                'name'           => data_get($payload, 'customer.name'),
                'phone'          => data_get($payload, 'customer.phone'),
                'email'          => data_get($payload, 'customer.email'),
                'ic_passport_no' => data_get($payload, 'customer.ic_passport_no'),
            ], fn ($v, $k) => $v !== null && $v !== $customer->{$k}, ARRAY_FILTER_USE_BOTH);

            if ($changed) {
                $customer->update($changed);
            }
        }

        $booking->update([
            'package_id'      => data_get($payload, 'booking.package_id'),
            'package_date_id' => data_get($payload, 'booking.package_date_id'),
            'travel_date'     => $travelDate,
            'pickup_location' => data_get($payload, 'booking.pickup_location'),
            'arrival_time'    => data_get($payload, 'booking.arrival_time'),
            'notes'           => data_get($payload, 'booking.notes'),
            'adults'          => array_sum(array_column($priced['lines'], 'adults')),
            'children'        => array_sum(array_column($priced['lines'], 'children')),
            'seniors'         => array_sum(array_column($priced['lines'], 'seniors')),
            'infants'         => array_sum(array_column($priced['lines'], 'infants')),
            'total_pax'       => array_sum(array_map(
                fn ($l) => $l['adults'] + $l['children'] + $l['seniors'] + $l['infants'],
                $priced['lines']
            )),
            'subtotal'     => $priced['subtotal'],
            'total_amount' => max(0, $priced['total'] - (float) $booking->discount),
        ]);

        // Room and pax rows carry no stable identity across a resubmit, so they are
        // rebuilt wholesale. Their prices are re-snapshotted from today's tiers.
        $booking->rooms()->delete();
        foreach ($priced['lines'] as $line) {
            $booking->rooms()->create($line);
        }

        $booking->pax()->delete();
        foreach (data_get($payload, 'pax', []) as $row) {
            if (empty($row['name'])) {
                continue;
            }
            $booking->pax()->create([
                'name'           => $row['name'],
                'type'           => $row['type'] ?? 'adult',
                'age'            => $row['age'] ?? null,
                'ic_passport_no' => $row['ic_passport_no'] ?? null,
                'dob'            => $row['dob'] ?? null,
                'nationality'    => $row['nationality'] ?? null,
                'is_lead'        => ! empty($row['is_lead']),
            ]);
        }

        $this->applyReceipt($booking, $payload, $actor);
    }

    /**
     * A replaced receipt supersedes the old payment instead of overwriting it — an older
     * version's snapshot still links to that file, and it is financial evidence.
     */
    private function applyReceipt(Booking $booking, array $payload, ?User $actor): void
    {
        $path = data_get($payload, 'payment.slip_path');
        $previousId = data_get($payload, 'payment.payment_id');
        $previous = $previousId ? Payment::find($previousId) : null;

        if (! $path || ($previous && $previous->slip_path === $path)) {
            return;
        }

        $payment = $booking->payments()->create([
            'amount'      => (float) (data_get($payload, 'payment.amount') ?? $previous?->amount ?? 0),
            'method'      => data_get($payload, 'payment.method') ?? $previous?->method ?? 'slip_upload',
            'type'        => $previous?->type ?? 'deposit',
            'reference'   => data_get($payload, 'payment.reference'),
            'slip_path'   => $path,
            'status'      => 'pending',
            'recorded_by' => $actor?->id,
        ]);

        // Chain, never mutate: the old row keeps its file and its audit value.
        $previous?->update(['superseded_by' => $payment->id]);
    }

    /**
     * No history rows yet means the booking predates the revision loop — record v1 now,
     * credited to whoever submitted it rather than to the admin triggering this.
     */
    public function ensureInitialVersion(Booking $booking): void
    {
        if ($booking->versions()->exists()) {
            return;
        }

        $booking->versions()->create([
            'created_by' => $booking->created_by,
            'version'    => 1,
            'reason'     => 'initial',
            'payload'    => $this->snapshot($booking),
        ]);
    }
}
