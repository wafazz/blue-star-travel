<?php

namespace App\Services;

use App\Models\Booking;
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
     * @return array<int, array<string, mixed>>
     */
    private function roomLines(Package $package, array $data, $fallbackPricing): array
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

    public function log(Booking $booking, ?User $actor, string $action, ?string $note): void
    {
        $booking->timeline()->create([
            'user_id' => $actor?->id,
            'status'  => $booking->status,
            'action'  => $action,
            'note'    => $note,
        ]);
    }
}
