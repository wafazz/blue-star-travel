<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Some passengers on a booking put off their trip. The booking itself stays confirmed and
 * its fare stays on it; these passengers hold no seat on the main departure until they are
 * given their own departure, which takes seats there.
 */
class BookingPostponement extends Model
{
    const PENDING = 'pending';
    const REDATED = 'redated';

    protected $guarded = [];

    protected $casts = [
        'redated_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function packageDate(): BelongsTo
    {
        return $this->belongsTo(PackageDate::class, 'package_date_id');
    }

    public function paxTotal(): int
    {
        return (int) $this->adults + (int) $this->children + (int) $this->seniors + (int) $this->infants;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
