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
        'included'    => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Sum of the included items per passenger. Deductions reduce it. */
    public static function perPaxFor(Booking $booking): float
    {
        return round((float) $booking->items()->where('included', true)->sum('unit_amount'), 2);
    }
}
