<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAmendment;
use App\Models\BookingRevisionRequest;
use App\Models\BookingTimeline;
use App\Models\BookingVersion;
use App\Models\Commission;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private BookingDocumentService $documents,
        private CommissionService $commissions,
        private GamificationService $game,
        private NotificationService $notifications,
        private CouponService $coupons,
        private TierService $tiers,
        private BookingRevisionService $revisions,
    ) {}

    public function reverseCommissions(Booking $booking, ?User $actor): void
    {
        $this->commissions->reverse($booking, $actor);
    }

    public function generateBookingNo(): string
    {
        $year = now()->format('Y');
        $prefix = "BK-{$year}-";
        $last = Booking::where('booking_no', 'like', $prefix . '%')->orderByDesc('id')->value('booking_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /** Total travellers in a booking request — summed across room lines when present. */
    private function paxTotal(array $data): int
    {
        $rows = ! empty($data['rooms']) ? $data['rooms'] : [$data];

        $total = 0;
        foreach ($rows as $r) {
            $total += (int) ($r['adults'] ?? 0) + (int) ($r['children'] ?? 0)
                + (int) ($r['seniors'] ?? 0) + (int) ($r['infants'] ?? 0);
        }

        return $total;
    }

    /**
     * Enforce the package's date_mode against a booking request, and check seats on a
     * chosen departure. Called by every portal before create() so the three booking
     * forms cannot drift apart. Throws ValidationException keyed to the offending field.
     */
    public function assertDateSelection(Package $package, array $data): void
    {
        // Pax live on the room lines now, so an empty room table means an empty booking.
        if ($this->paxTotal($data) < 1) {
            throw ValidationException::withMessages([
                'rooms' => 'Add at least one passenger.',
            ]);
        }

        $dateId = $data['package_date_id'] ?? null;
        $travelDate = $data['travel_date'] ?? null;

        if ($dateId) {
            if ($package->date_mode === 'open') {
                throw ValidationException::withMessages([
                    'package_date_id' => 'This package is open-dated — pick a travel date instead of a departure.',
                ]);
            }

            $departure = $package->dates()->find($dateId);
            if (! $departure || $departure->package_id !== $package->id) {
                throw ValidationException::withMessages([
                    'package_date_id' => 'That departure does not belong to this package.',
                ]);
            }
            if ($departure->status !== 'open') {
                throw ValidationException::withMessages([
                    'package_date_id' => 'That departure is ' . $departure->status . ' and cannot be booked.',
                ]);
            }

            $pax = $this->paxTotal($data);

            // seats_total 0 means "unlimited" — the seeded showcase uses it that way.
            if ($departure->seats_total > 0 && $pax > $departure->seatsAvailable()) {
                throw ValidationException::withMessages([
                    'package_date_id' => 'Only ' . $departure->seatsAvailable() . ' seat(s) left on that departure — you asked for ' . $pax . '.',
                ]);
            }

            return;
        }

        if ($package->requiresDeparture()) {
            throw ValidationException::withMessages([
                'package_date_id' => 'This package runs on scheduled departures — choose one.',
            ]);
        }

        if (! $travelDate) {
            throw ValidationException::withMessages([
                'travel_date' => 'Choose your travel date.',
            ]);
        }
    }

    /**
     * Normalise a booking's room selection into priced lines. Each line is one room type
     * at its own per-pax rate; `rooms` is how many physical rooms of that type. Falls back
     * to a single line on the chosen pricing tier when no rooms were posted.
     *
     * Public because the revision service must re-price a resubmission through exactly
     * this path — a second implementation would drift from what new bookings charge.
     *
     * @return array<int, array<string, mixed>>
     */
    public function roomLines(Package $package, array $data, $fallbackPricing): array
    {
        $posted = array_values(array_filter(
            $data['rooms'] ?? [],
            fn ($r) => (int) ($r['adults'] ?? 0) + (int) ($r['children'] ?? 0)
                + (int) ($r['seniors'] ?? 0) + (int) ($r['infants'] ?? 0) > 0
        ));

        if (empty($posted)) {
            $posted = [[
                'package_pricing_id' => $fallbackPricing?->id,
                'adults'   => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'seniors'  => $data['seniors'] ?? 0,
                'infants'  => $data['infants'] ?? 0,
            ]];
        }

        $lines = [];
        foreach ($posted as $row) {
            $pricing = $package->pricings()->find($row['package_pricing_id'] ?? null) ?? $fallbackPricing;

            $adults   = (int) ($row['adults'] ?? 0);
            $children = (int) ($row['children'] ?? 0);
            $seniors  = (int) ($row['seniors'] ?? 0);
            $infants  = (int) ($row['infants'] ?? 0);

            $adultPrice  = (float) ($pricing->promo_price ?? $pricing->adult_price ?? 0);
            $childPrice  = (float) ($pricing->child_price ?? 0);
            $seniorPrice = (float) ($pricing->senior_price ?: 0) ?: $adultPrice;
            $infantPrice = (float) ($pricing->infant_price ?? 0);

            $capacity = max(1, (int) ($pricing->capacity ?? 2));
            $pax      = $adults + $children + $seniors + $infants;

            $lines[] = [
                'package_pricing_id' => $pricing?->id,
                'room_name'   => $pricing->tier_name ?? 'Standard',
                'capacity'    => $capacity,
                // Infants share a bed, so they never force an extra room.
                'rooms'       => (int) ceil(max(1, $pax - $infants) / $capacity),
                'adults'      => $adults,
                'children'    => $children,
                'seniors'     => $seniors,
                'infants'     => $infants,
                'adult_price'  => $adultPrice,
                'child_price'  => $childPrice,
                'senior_price' => $seniorPrice,
                'infant_price' => $infantPrice,
                'subtotal'    => round($adults * $adultPrice + $children * $childPrice
                    + $seniors * $seniorPrice + $infants * $infantPrice, 2),
            ];
        }

        return $lines;
    }

    public function create(array $data, ?User $actor, array $paxRows = []): Booking
    {
        return DB::transaction(function () use ($data, $actor, $paxRows) {
            $package = Package::findOrFail($data['package_id']);
            $pricing = $package->pricings()->find($data['package_pricing_id'] ?? null) ?? $package->defaultPricing();

            // A departure fixes the travel date — never let the two disagree.
            $departure = ! empty($data['package_date_id']) ? $package->dates()->find($data['package_date_id']) : null;
            if ($departure) {
                $data['travel_date'] = $departure->depart_date;
            }

            // A booking may span several room types, each with its own per-pax rate.
            // Without room lines this falls back to one line on the chosen tier, which
            // is what the customer portal and the seeders still post.
            $roomLines = $this->roomLines($package, $data, $pricing);

            $adults   = array_sum(array_column($roomLines, 'adults'));
            $children = array_sum(array_column($roomLines, 'children'));
            $seniors  = array_sum(array_column($roomLines, 'seniors'));
            $infants  = array_sum(array_column($roomLines, 'infants'));

            // Legacy single-rate columns keep the first room's rates so older screens,
            // exports and the commission fallback still read something sensible.
            $adultPrice  = $roomLines[0]['adult_price'];
            $childPrice  = $roomLines[0]['child_price'];
            $seniorPrice = $roomLines[0]['senior_price'];
            $infantPrice = $roomLines[0]['infant_price'];

            $subtotal = array_sum(array_column($roomLines, 'subtotal'));
            $discount = (float) ($data['discount'] ?? 0);

            // Optional coupon — validated against the computed subtotal.
            $couponId = null;
            if (! empty($data['coupon_code'])) {
                $coupon = $this->coupons->validate($data['coupon_code'], $subtotal);
                $discount += $coupon->discountFor($subtotal);
                $couponId = $coupon->id;
                $this->coupons->redeem($coupon);
            }

            $total    = max(0, $subtotal - $discount);
            $status = ($data['status'] ?? 'pending_verification') === 'draft' ? 'draft' : 'pending_verification';

            $booking = Booking::create([
                'booking_no'         => $this->generateBookingNo(),
                'package_id'         => $package->id,
                'package_date_id'    => $data['package_date_id'] ?? null,
                'package_pricing_id' => $pricing?->id,
                'customer_id'        => $data['customer_id'],
                'agent_id'           => $data['agent_id'] ?? ($actor && $actor->hasRole('agent') ? $actor->id : null),
                'provider_id'        => $package->provider_id,
                'created_by'         => $actor?->id,
                'type'               => $data['type'] ?? 'online',
                'status'             => $status,
                'travel_date'        => $data['travel_date'] ?? null,
                'pickup_location'    => $data['pickup_location'] ?? null,
                'arrival_time'       => $data['arrival_time'] ?? null,
                'adults'             => $adults,
                'children'           => $children,
                'seniors'            => $seniors,
                'infants'            => $infants,
                'total_pax'          => $adults + $children + $seniors + $infants,
                'adult_price'        => $adultPrice,
                'child_price'        => $childPrice,
                'senior_price'       => $seniorPrice,
                'infant_price'       => $infantPrice,
                'subtotal'           => $subtotal,
                'discount'           => $discount,
                'coupon_id'          => $couponId,
                'total_amount'       => $total,
                'notes'              => $data['notes'] ?? null,
                'submitted_at'       => $status === 'draft' ? null : now(),
            ]);

            foreach ($roomLines as $line) {
                $booking->rooms()->create($line);
            }

            foreach ($paxRows as $row) {
                if (empty($row['name'])) {
                    continue;
                }
                $booking->pax()->create([
                    'name'           => $row['name'],
                    'type'           => $row['type'] ?? 'adult',
                    'ic_passport_no' => $row['ic_passport_no'] ?? null,
                    'nationality'    => $row['nationality'] ?? null,
                    'is_lead'        => ! empty($row['is_lead']),
                ]);
            }

            $this->log($booking, $actor, $status === 'draft' ? 'Booking drafted' : 'Booking submitted', null);

            return $booking;
        });
    }

    public function submitToProvider(Booking $booking, ?User $actor): void
    {
        $booking->update([
            'status'              => 'waiting_provider_confirmation',
            'provider_status'     => 'pending',
            'sent_to_provider_at' => now(),
        ]);
        $this->log($booking, $actor, 'Verified & sent to provider', $booking->provider?->name);
    }

    public function providerRespond(Booking $booking, ?User $actor, string $decision, ?string $note = null): void
    {
        $approved = $decision === 'approved';
        $booking->update([
            'provider_status'       => $approved ? 'approved' : 'rejected',
            'provider_note'         => $note,
            'provider_responded_at' => now(),
        ]);
        $this->log($booking, $actor, 'Provider ' . ($approved ? 'approved' : 'rejected') . ' the booking', $note);
    }

    public function confirm(Booking $booking, ?User $actor): void
    {
        // The agent is mid-edit; confirming now would freeze data they are about to change.
        if ($booking->revisionRequests()->where('status', 'open')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This booking is out with the agent for revision — wait for them to resubmit.',
            ]);
        }

        DB::transaction(function () use ($booking, $actor) {
            $booking->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);

            if ($booking->packageDate) {
                $booking->packageDate->increment('seats_booked', $booking->total_pax);
            }

            $this->documents->generateInvoice($booking);
            $this->documents->generateVoucher($booking);

            $this->log($booking, $actor, 'Booking confirmed — invoice & travel voucher issued', null);
            $this->notifyParties($booking, 'booking', "Booking {$booking->booking_no} confirmed",
                'Invoice & travel voucher are ready to download.');
        });
    }

    /**
     * Bounce a submitted booking back to the agent with a remark and the fields to fix.
     * Only reachable while the money is still unsettled — see assertRevisable().
     */
    public function requestRevision(Booking $booking, ?User $actor, ?string $remark, array $fields = []): void
    {
        DB::transaction(function () use ($booking, $actor, $remark, $fields) {
            $this->assertRevisable($booking);

            // Record what the agent originally submitted before they start changing it,
            // so v1 always exists to diff the first resubmission against.
            $this->revisions->ensureInitialVersion($booking);

            if ($booking->status === 'waiting_provider_confirmation') {
                $this->recallFromProvider($booking, $actor);
            }

            // Any earlier round that is still open is superseded by this one.
            $booking->revisionRequests()->where('status', 'open')->update([
                'status'      => 'cancelled',
                'resolved_at' => now(),
            ]);

            $request = $booking->revisionRequests()->create([
                'requested_by' => $actor?->id,
                'remark'       => $remark,
                'fields'       => array_values(array_intersect($fields, array_keys(BookingRevisionRequest::FIELDS))),
                'status'       => 'open',
            ]);

            $booking->update([
                'status'                => 'needs_revision',
                'revision_requested_at' => now(),
            ]);

            $labels = $request->fieldLabels();
            $note = $remark . ($labels ? ' — fields: ' . implode(', ', $labels) : '');
            $this->log($booking, $actor, 'Revision requested from agent', trim($note) ?: null);

            // Agent only. Whether the customer should see "Need Revision" is still open
            // (Planning §13.8 Q8), and telling them first would be the wrong default.
            if ($booking->agent) {
                $this->notifications->notify(
                    $booking->agent,
                    'booking',
                    "Booking {$booking->booking_no} needs revision",
                    $remark,
                    route('agent.bookings.show', $booking)
                );
            }
        });
    }

    /**
     * Commit a staged edit: apply it, snapshot the new version, and hand the booking back
     * to staff. One transaction, booking row locked, status re-asserted after the lock.
     */
    public function resubmitRevision(Booking $booking, ?User $actor): BookingVersion
    {
        return DB::transaction(function () use ($booking, $actor) {
            // Re-read under the lock — staff may have actioned this while the agent typed.
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isEditableByAgent()) {
                throw ValidationException::withMessages([
                    'status' => 'This booking was actioned while you were editing — reopen it to see the current state.',
                ]);
            }

            $draft = $this->revisions->draftFor($locked, $actor);
            if (! $draft) {
                throw ValidationException::withMessages([
                    'status' => 'There are no staged changes to submit.',
                ]);
            }

            $payload = $draft->payload;
            $this->assertResubmittable($locked, $payload);

            // An edited booking goes back into the verification queue, so whatever it held
            // as a confirmed booking must be released first — otherwise re-confirming
            // increments the same seats a second time.
            $wasConfirmed = $locked->status === 'confirmed';
            $totalBefore = (float) $locked->total_amount;
            if ($wasConfirmed && $locked->packageDate) {
                $locked->packageDate->decrement(
                    'seats_booked',
                    min($locked->total_pax, $locked->packageDate->seats_booked)
                );
            }

            // Diff BEFORE applying. apply() rebuilds booking_pax wholesale, so afterwards
            // every passenger carries a new id — diffing post-apply would report the whole
            // manifest as removed-and-re-added on every single resubmission.
            $changes = $this->revisions->diff($this->revisions->latestVersionPayload($locked), $payload);
            $requestId = $locked->openRevisionRequest?->id;

            $this->revisions->apply($locked, $payload, $actor);
            $locked->refresh();

            // The stored payload is re-snapshotted from the applied record, so it carries
            // the real ids and the re-derived money rather than the draft's indicative copy.
            $version = $this->revisions->snapshotVersion(
                $locked,
                $actor,
                'revision',
                $this->revisions->snapshot($locked),
                $changes,
                $requestId
            );

            $locked->revisionRequests()->where('status', 'open')->update([
                'status'      => 'resolved',
                'resolved_at' => now(),
            ]);

            // Every resubmission lands back on "Submitted" for re-verification, whatever
            // it was before — including a booking that had already been confirmed.
            $locked->update([
                'status'          => 'pending_verification',
                'provider_status' => 'pending',
                'resubmitted_at'  => now(),
                'revision_no'     => $version->version - 1,
                'confirmed_at'    => $wasConfirmed ? null : $locked->confirmed_at,
            ]);

            $draft->delete();

            // Same policy as an approved amendment: a price change on a booking that has
            // already earned commission reverses the old figures and recomputes them.
            if ((float) $locked->total_amount !== $totalBefore
                && Commission::where('booking_id', $locked->id)->where('status', '!=', 'reversed')->exists()) {
                $this->commissions->reverse($locked, $actor);
                $this->commissions->calculate($locked);
                $this->log($locked, $actor, 'Commission reversed and recalculated after edit', null);
            }

            $this->log($locked, $actor, "Agent resubmitted (v{$version->version})",
                count($changes) . ' item(s) updated')->update(['booking_version_id' => $version->id]);

            if ($wasConfirmed) {
                $this->log($locked, $actor, 'Confirmed booking reopened for re-verification',
                    'Seats released; invoice & voucher reissue on re-confirmation.');
            }

            return $version;
        });
    }

    /**
     * Agent asks for a change to an already-confirmed booking. Nothing moves until staff
     * approve — a confirmed booking holds seats, an issued invoice and possibly commission.
     */
    public function requestAmendment(Booking $booking, ?User $actor, array $data): BookingAmendment
    {
        if ($booking->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'status' => 'Only a confirmed booking can be amended.',
            ]);
        }

        if ($booking->amendments()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'There is already an amendment awaiting review on this booking.',
            ]);
        }

        $amendment = $booking->amendments()->create($data + [
            'requested_by'  => $actor?->id,
            'status'        => 'pending',
            'current_value' => $this->amendmentCurrentValue($booking, $data['type'] ?? 'other'),
        ]);

        $this->log($booking, $actor, 'Amendment requested — ' . $amendment->typeLabel(), $amendment->reason);

        foreach (User::whereIn('role', ['super_admin', 'hq', 'admin'])->where('status', 'active')->get() as $staff) {
            $this->notifications->notify($staff, 'booking',
                "Amendment requested on {$booking->booking_no}",
                $amendment->typeLabel() . ' — ' . $amendment->reason,
                route('manage.bookings.show', $booking));
        }

        return $amendment;
    }

    private function amendmentCurrentValue(Booking $booking, string $type): ?string
    {
        return match ($type) {
            'travel_date' => optional($booking->travel_date)->format('d M Y')
                ?? optional($booking->packageDate?->depart_date)->format('d M Y'),
            'pickup' => trim(($booking->pickup_location ?? '—') . ' · '
                . ($booking->arrival_time ? substr($booking->arrival_time, 0, 5) : '—')),
            default => null,
        };
    }

    /**
     * Approve and apply. The booking stays `confirmed` throughout — seats move between
     * departures, a new version is snapshotted, and documents are reissued.
     */
    public function approveAmendment(BookingAmendment $amendment, ?User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($amendment, $actor, $note) {
            $booking = Booking::whereKey($amendment->booking_id)->lockForUpdate()->first();

            if ($amendment->status !== 'pending' || $booking?->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => 'This amendment can no longer be approved.',
                ]);
            }

            $before = $this->revisions->latestVersionPayload($booking);
            $totalBefore = (float) $booking->total_amount;

            if ($amendment->isAutoApplied()) {
                $this->applyAmendment($booking, $amendment);
            }

            $amendment->update([
                'status'      => 'approved',
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
                'admin_note'  => $note,
            ]);

            $booking->refresh();
            $after = $this->revisions->snapshot($booking);
            $version = $this->revisions->snapshotVersion($booking, $actor, 'amendment', $after,
                $this->revisions->diff($before, $after));

            // Documents carry the old date — reissue so the traveller's voucher is right.
            if ($amendment->isAutoApplied()) {
                $this->documents->generateInvoice($booking);
                $this->documents->generateVoucher($booking);
            }

            // Price moved on a booking that already earned commission: reverse the old
            // figures and recompute against the new total, per the agreed policy.
            if ((float) $booking->total_amount !== $totalBefore
                && Commission::where('booking_id', $booking->id)->where('status', '!=', 'reversed')->exists()) {
                $this->commissions->reverse($booking, $actor);
                $this->commissions->calculate($booking);
                $this->log($booking, $actor, 'Commission reversed and recalculated after amendment', null);
            }

            $this->log($booking, $actor, 'Amendment approved — ' . $amendment->typeLabel(), $note)
                ->update(['booking_version_id' => $version->id]);

            $this->notifyParties($booking, 'booking',
                "Amendment approved on {$booking->booking_no}", $amendment->typeLabel());
        });
    }

    /** Move the requested change onto the live booking, including the seat transfer. */
    private function applyAmendment(Booking $booking, BookingAmendment $amendment): void
    {
        if ($amendment->type === 'pickup') {
            $booking->update([
                'pickup_location' => $amendment->requested_pickup_location,
                'arrival_time'    => $amendment->requested_arrival_time,
            ]);

            return;
        }

        $oldDate = $booking->packageDate;
        $newDate = $amendment->packageDate;

        if ($newDate) {
            // The booking already holds its own seats on the old departure, so its pax
            // must be added back before asking whether the new one has room — otherwise
            // it counts itself as competition.
            $ownSeats = $oldDate && $oldDate->id === $newDate->id ? $booking->total_pax : 0;
            if ($newDate->seats_total > 0 && $booking->total_pax > $newDate->seatsAvailable() + $ownSeats) {
                throw ValidationException::withMessages([
                    'requested_package_date_id' => 'Only ' . $newDate->seatsAvailable()
                        . ' seat(s) left on that departure — this booking needs ' . $booking->total_pax . '.',
                ]);
            }

            // Release the old departure only if there was one, but always take the new
            // one — a booking moving from "no departure" onto a date must still hold seats.
            if (! $oldDate || $oldDate->id !== $newDate->id) {
                $oldDate?->decrement('seats_booked', min($booking->total_pax, $oldDate->seats_booked));
                $newDate->increment('seats_booked', $booking->total_pax);
            }
        }

        $booking->update([
            'package_date_id' => $newDate?->id ?? $booking->package_date_id,
            'travel_date'     => $newDate?->depart_date ?? $amendment->requested_date,
        ]);
    }

    public function rejectAmendment(BookingAmendment $amendment, ?User $actor, ?string $note = null): void
    {
        if ($amendment->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This amendment has already been reviewed.']);
        }

        $amendment->update([
            'status'      => 'rejected',
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
            'admin_note'  => $note,
        ]);

        $this->log($amendment->booking, $actor, 'Amendment rejected — ' . $amendment->typeLabel(), $note);
        $this->notifyParties($amendment->booking, 'booking',
            "Amendment rejected on {$amendment->booking->booking_no}", $note);
    }

    /** Money and seat rules that must hold before a staged edit can go live. */
    public function assertResubmittable(Booking $booking, array $payload): void
    {
        $package = Package::findOrFail(data_get($payload, 'booking.package_id'));

        // Seats are only held from confirm(), so re-check the *new* departure loudly
        // rather than letting a silently overbooked date through.
        // Rooms must go along — assertDateSelection also enforces "at least one passenger",
        // which reads the pax off the room lines.
        $this->assertDateSelection($package, [
            'package_date_id' => data_get($payload, 'booking.package_date_id'),
            'travel_date'     => data_get($payload, 'booking.travel_date'),
            'rooms'           => data_get($payload, 'rooms', []),
        ]);

        // balance() goes negative and isFullyPaid() starts returning true — silently wrong.
        $newTotal = max(0, $this->revisions->price($payload)['total'] - (float) $booking->discount);
        if ($newTotal < (float) $booking->paid_amount) {
            throw ValidationException::withMessages([
                'rooms' => 'This change drops the total below what has already been paid (RM '
                    . number_format($booking->paid_amount, 2) . '). Request a refund first.',
            ]);
        }
    }

    /**
     * Pull a booking back off the provider's queue so they never act on data the agent
     * is about to change. Inverse of submitToProvider().
     */
    public function recallFromProvider(Booking $booking, ?User $actor): void
    {
        $booking->update([
            'provider_status'       => 'pending',
            'sent_to_provider_at'   => null,
            'provider_responded_at' => null,
            'provider_note'         => null,
        ]);
        $this->log($booking, $actor, 'Recalled from provider for revision', $booking->provider?->name);
    }

    /** Guards that make a revision safe to request. Throws, so callers get one rule. */
    public function assertRevisable(Booking $booking): void
    {
        if (! in_array($booking->status, ['pending_verification', 'waiting_provider_confirmation'])) {
            throw ValidationException::withMessages([
                'status' => 'Only a submitted booking can be sent back for revision.',
            ]);
        }

        // Commission is calculated once and refuses to recalculate, so a re-priced
        // resubmission would silently keep the old figure. Amendments handle that path.
        if (Commission::where('booking_id', $booking->id)->where('status', '!=', 'reversed')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Commission has already been calculated on this booking — cancel or amend it instead.',
            ]);
        }

        // The provider has already committed and money has moved; recalling now would
        // desync a real-world reservation.
        if ($booking->provider_status === 'approved' && (float) $booking->paid_amount > 0) {
            throw ValidationException::withMessages([
                'status' => 'The provider approved this booking and payment has been taken — amend or cancel instead.',
            ]);
        }
    }

    public function reject(Booking $booking, ?User $actor, ?string $reason = null): void
    {
        $booking->update([
            'status'           => 'rejected',
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);
        $this->log($booking, $actor, 'Booking rejected', $reason);
        $this->notifyParties($booking, 'booking', "Booking {$booking->booking_no} rejected", $reason);
    }

    /** Notify the selling agent (and customer's user account, if any) about a booking event. */
    private function notifyParties(Booking $booking, string $type, string $title, ?string $body): void
    {
        if ($booking->agent) {
            $this->notifications->notify($booking->agent, $type, $title, $body, route('agent.bookings.show', $booking));
        }
        $customerUser = optional($booking->customer)->user;
        if ($customerUser) {
            $this->notifications->notify($customerUser, $type, $title, $body, route('customer.dashboard'));
        }
    }

    public function complete(Booking $booking, ?User $actor): void
    {
        $booking->update(['status' => 'completed', 'completed_at' => now()]);
        $this->log($booking, $actor, 'Booking marked as completed', null);
    }

    public function cancel(Booking $booking, ?User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($booking, $actor, $reason) {
            if ($booking->status === 'confirmed' && $booking->packageDate) {
                $booking->packageDate->decrement('seats_booked', min($booking->total_pax, $booking->packageDate->seats_booked));
            }
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->commissions->reverse($booking, $actor);
            $this->log($booking, $actor, 'Booking cancelled', $reason);
        });
    }

    public function recordPayment(Booking $booking, array $data, ?User $actor): Payment
    {
        $payment = $booking->payments()->create([
            'recorded_by' => $actor?->id,
            'reference'   => $data['reference'] ?? null,
            'amount'      => $data['amount'] ?? 0,
            'method'      => $data['method'] ?? 'slip_upload',
            'type'        => $data['type'] ?? 'full',
            'status'      => 'pending',
            'slip_path'   => $data['slip_path'] ?? null,
            'note'        => $data['note'] ?? null,
            'paid_at'     => $data['paid_at'] ?? now(),
        ]);

        $this->log($booking, $actor, 'Payment recorded (RM ' . number_format((float) $payment->amount, 2) . ') — pending verification', $payment->methodLabel());

        return $payment;
    }

    public function verifyPayment(Payment $payment, ?User $actor): void
    {
        DB::transaction(function () use ($payment, $actor) {
            $payment->update(['status' => 'verified', 'verified_by' => $actor?->id, 'verified_at' => now()]);
            $booking = $payment->booking;
            $this->recalcPaid($booking);
            $this->log($booking, $actor, 'Payment verified (RM ' . number_format((float) $payment->amount, 2) . ')', null);

            if ($booking->fresh()->isFullyPaid() && $booking->total_amount > 0) {
                $this->documents->generateReceipt($booking->fresh());
                $this->log($booking->fresh(), $actor, 'Payment complete — official receipt issued', null);
                $this->commissions->calculate($booking->fresh());

                if ($booking->agent) {
                    $this->game->completeMissionByCode($booking->agent, 'complete_booking');
                    $this->game->evaluateAchievements($booking->agent);
                    $this->tiers->evaluate($booking->agent, $actor); // sales-driven rank promotion
                }
            }
        });
    }

    public function rejectPayment(Payment $payment, ?User $actor): void
    {
        $payment->update(['status' => 'rejected', 'verified_by' => $actor?->id, 'verified_at' => now()]);
        $this->recalcPaid($payment->booking);
        $this->log($payment->booking, $actor, 'Payment rejected', null);
    }

    public function recalcPaid(Booking $booking): void
    {
        $paid = $booking->payments()->where('status', 'verified')->sum('amount');
        $booking->update(['paid_amount' => $paid]);
    }

    /** Returns the row so callers that need to link it (e.g. to a version) need not re-query. */
    public function log(Booking $booking, ?User $actor, string $action, ?string $note): BookingTimeline
    {
        return $booking->timeline()->create([
            'user_id' => $actor?->id,
            'status'  => $booking->status,
            'action'  => $action,
            'note'    => $note,
        ]);
    }
}
