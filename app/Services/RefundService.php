<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(private BookingService $bookings) {}

    public function generateRefundNo(): string
    {
        $prefix = 'RF-' . now()->format('Y') . '-';
        $last = Refund::where('refund_no', 'like', $prefix . '%')->orderByDesc('id')->value('refund_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function request(Booking $booking, array $data, ?User $actor): Refund
    {
        $refund = $booking->refunds()->create([
            'refund_no'    => $this->generateRefundNo(),
            'payment_id'   => $data['payment_id'] ?? null,
            'requested_by' => $actor?->id,
            'amount'       => round((float) ($data['amount'] ?? 0), 2),
            'method'       => $data['method'] ?? 'bank_transfer',
            'status'       => 'pending',
            'reason'       => $data['reason'] ?? null,
        ]);

        $this->bookings->log($booking, $actor, 'Refund requested (RM ' . number_format((float) $refund->amount, 2) . ')', $refund->reason);

        return $refund;
    }

    public function approve(Refund $refund, ?User $actor, ?string $note = null): void
    {
        $refund->update(['status' => 'approved', 'approved_by' => $actor?->id, 'admin_note' => $note]);
        $this->bookings->log($refund->booking, $actor, 'Refund approved', $note);
    }

    public function reject(Refund $refund, ?User $actor, ?string $note = null): void
    {
        $refund->update(['status' => 'rejected', 'approved_by' => $actor?->id, 'admin_note' => $note]);
        $this->bookings->log($refund->booking, $actor, 'Refund rejected', $note);
    }

    public function process(Refund $refund, ?User $actor): void
    {
        DB::transaction(function () use ($refund, $actor) {
            $refund->update(['status' => 'processed', 'processed_at' => now()]);

            $booking = $refund->booking;
            $this->bookings->recalcPaid($booking);

            // A refund that clears the net paid marks the whole booking refunded
            // and claws back any commission earned on it.
            if ($booking->refundedAmount() >= (float) $booking->paid_amount && (float) $booking->paid_amount > 0) {
                $booking->update(['status' => 'refunded']);
                $this->bookings->reverseCommissions($booking, $actor);
            }

            $this->bookings->log($booking, $actor, 'Refund processed (RM ' . number_format((float) $refund->amount, 2) . ') via ' . $refund->methodLabel(), null);
        });
    }
}
