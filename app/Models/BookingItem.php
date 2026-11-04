<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A service or charge staff add to a booking by hand: snorkelling, full board, water taxi… */
class BookingItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'unit_amount' => 'decimal:2',
        'quantity'    => 'integer',
        'included'    => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Money the included items add to a booking of this many packs. An item with a
     * quantity applies to that many packs; one with none applies to every pack.
     * A deduction is a negative unit amount, so it reduces the total.
     */
    public static function totalFor(Booking $booking, int $paxTotal): float
    {
        return round((float) $booking->items()->where('included', true)->get()
            ->sum(fn (self $item) => (float) $item->unit_amount * ($item->quantity ?? $paxTotal)), 2);
    }
}
