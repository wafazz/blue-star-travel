<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private BookingDocumentService $documents,
        private CommissionService $commissions,
        private GamificationService $game,
        private NotificationService $notifications,
        private CouponService $coupons,
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

    public function create(array $data, ?User $actor, array $paxRows = []): Booking
    {
        return DB::transaction(function () use ($data, $actor, $paxRows) {
            $package = Package::findOrFail($data['package_id']);
            $pricing = $package->pricings()->find($data['package_pricing_id'] ?? null) ?? $package->defaultPricing();

            $adults   = (int) ($data['adults'] ?? 1);
            $children = (int) ($data['children'] ?? 0);
            $infants  = (int) ($data['infants'] ?? 0);

            $adultPrice  = $pricing->promo_price ?? $pricing->adult_price;
            $childPrice  = $pricing->child_price;
            $infantPrice = $pricing->infant_price;

            $subtotal = ($adults * $adultPrice) + ($children * $childPrice) + ($infants * $infantPrice);
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
                'infants'            => $infants,
                'total_pax'          => $adults + $children + $infants,
                'adult_price'        => $adultPrice,
                'child_price'        => $childPrice,
                'infant_price'       => $infantPrice,
                'subtotal'           => $subtotal,
                'discount'           => $discount,
                'coupon_id'          => $couponId,
                'total_amount'       => $total,
                'notes'              => $data['notes'] ?? null,
                'submitted_at'       => $status === 'draft' ? null : now(),
            ]);

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
