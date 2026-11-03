<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPostponement;
use App\Models\PackageDate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Part of a booking puts its trip off. The fare stays on the booking (no refund, no
 * re-price); the passengers give up their seat on the main departure now and take one
 * on their own departure when they are redated.
 */
class PartialPostponementService
{
    const TYPES = ['adults' => 'adult', 'children' => 'child', 'seniors' => 'senior', 'infants' => 'infant'];

    public function __construct(private BookingService $bookings) {}

    public function postpone(Booking $booking, array $counts, ?string $reason, User $actor): BookingPostponement
    {
        return DB::transaction(function () use ($booking, $counts, $reason, $actor) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);

            $group = [];
            foreach (array_keys(self::TYPES) as $type) {
                $group[$type] = max(0, (int) ($counts[$type] ?? 0));
            }
            $n = array_sum($group);

            if ($n < 1) {
                throw ValidationException::withMessages(['adults' => 'Choose at least one passenger to postpone.']);
            }

            $existing = $locked->postponements()->get();
            foreach (self::TYPES as $type => $label) {
                $left = (int) $locked->{$type} - (int) $existing->sum($type);
                if ($group[$type] > $left) {
                    throw ValidationException::withMessages([$type => "Only {$left} {$label} passenger(s) left to postpone."]);
                }
            }

            if ($n >= $locked->mainPax()) {
                throw ValidationException::withMessages([
                    'adults' => 'That is everyone still travelling. Use the whole-booking postponement instead.',
                ]);
            }

            // Seats held on the main departure are released for the passengers put off.
            if ($locked->packageDate) {
                $locked->packageDate->decrement('seats_booked', min($n, $locked->packageDate->seats_booked));
            }

            $postponement = $locked->postponements()->create($group + [
                'status'     => BookingPostponement::PENDING,
                'reason'     => $reason,
                'created_by' => $actor->id,
            ]);

            $this->bookings->log($locked, $actor,
                "{$n} passenger(s) postponed — new travel date pending", $reason);

            return $postponement;
        });
    }

    /** Give the put-off passengers their own departure. Seats are taken there. */
    public function redate(BookingPostponement $postponement, int $departureId, User $actor): void
    {
        DB::transaction(function () use ($postponement, $departureId, $actor) {
            $locked = Booking::whereKey($postponement->booking_id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);

            $group = BookingPostponement::whereKey($postponement->id)->lockForUpdate()->firstOrFail();
            if (! $group->isPending()) {
                throw ValidationException::withMessages(['package_date_id' => 'These passengers already have a new date.']);
            }

            $departure = PackageDate::whereKey($departureId)->lockForUpdate()->firstOrFail();
            if ($departure->package_id !== $locked->package_id) {
                throw ValidationException::withMessages(['package_date_id' => 'That departure does not belong to this package.']);
            }
            if ($departure->status !== 'open') {
                throw ValidationException::withMessages(['package_date_id' => 'That departure is ' . $departure->status . ' and cannot be booked.']);
            }

            $pax = $group->paxTotal();
            if ($departure->seats_total > 0 && $pax > $departure->seatsAvailable()) {
                throw ValidationException::withMessages([
                    'package_date_id' => 'Only ' . $departure->seatsAvailable() . ' seat(s) left on that departure — these passengers need ' . $pax . '.',
                ]);
            }

            $departure->increment('seats_booked', $pax);
            $group->update([
                'package_date_id' => $departure->id,
                'status'          => BookingPostponement::REDATED,
                'redated_at'      => now(),
                'redated_by'      => $actor->id,
            ]);

            $this->bookings->log($locked, $actor,
                "{$pax} postponed passenger(s) given a new date — " . $departure->depart_date?->format('d M Y'), null);
        });
    }

    private function assertOpen(Booking $booking): void
    {
        if ($booking->status !== 'confirmed' || ! $booking->package_date_id) {
            throw ValidationException::withMessages([
                'status' => 'Passengers can only be postponed on a confirmed booking with a scheduled departure.',
            ]);
        }
    }
}
