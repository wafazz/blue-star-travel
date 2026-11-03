<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff price items (snorkelling, full board, water taxi…). An included item moves the
 * total and the percentage commission; an excluded one is kept for the record only.
 * The deposit is never touched — paid_amount does not change here.
 */
class BookingItemService
{
    public function __construct(
        private CommissionService $commissions,
        private BookingService $bookings,
    ) {}

    public function add(Booking $booking, array $data, User $actor): BookingItem
    {
        return DB::transaction(function () use ($booking, $data, $actor) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);

            $item = $locked->items()->create([
                'name'        => $data['name'],
                'unit_amount' => $data['unit_amount'],
                'included'    => (bool) ($data['included'] ?? true),
                'created_by'  => $actor->id,
            ]);

            $this->recalculate($locked, $actor, "Price item added — {$item->name}");

            return $item;
        });
    }

    public function setIncluded(BookingItem $item, bool $included, User $actor): void
    {
        DB::transaction(function () use ($item, $included, $actor) {
            $booking = Booking::whereKey($item->booking_id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($booking);

            if ($item->included === $included) {
                return;
            }

            $item->update(['included' => $included]);
            $this->recalculate($booking, $actor, ($included ? 'Price item included — ' : 'Price item excluded — ') . $item->name);
        });
    }

    public function remove(BookingItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor) {
            $booking = Booking::whereKey($item->booking_id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($booking);

            $name = $item->name;
            $item->delete();
            $this->recalculate($booking, $actor, "Price item removed — {$name}");
        });
    }

    /**
     * total = room subtotal − discount + included items × passengers. Recomputed from the
     * stored figures every time, so the total never drifts from its parts.
     */
    private function recalculate(Booking $booking, User $actor, string $action): void
    {
        $booking->refresh();
        $old = (float) $booking->total_amount;
        $new = max(0, round((float) $booking->subtotal - (float) $booking->discount + $booking->itemsTotal((int) $booking->total_pax), 2));

        $booking->update(['total_amount' => $new]);

        // Same rule as a revised price: commission already earned on the old total is
        // reversed and earned again on the new one.
        $moved = abs($new - $old) > 0.009;
        if ($moved && Commission::where('booking_id', $booking->id)->where('status', '!=', 'reversed')->exists()) {
            $this->commissions->reverse($booking, $actor);
            $this->commissions->calculate($booking);
        }

        $this->bookings->log($booking, $actor, $action,
            $moved ? sprintf('Total RM %s → RM %s', number_format($old, 2), number_format($new, 2)) : null);
    }

    private function assertOpen(Booking $booking): void
    {
        if (in_array($booking->status, Booking::AGENT_LOCKED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This booking is closed — its price items can no longer change.',
            ]);
        }
    }
}
