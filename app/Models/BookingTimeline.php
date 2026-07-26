<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTimeline extends Model
{
    protected $table = 'booking_timeline';

    protected $guarded = [];

    public function version(): BelongsTo
    {
        return $this->belongsTo(BookingVersion::class, 'booking_version_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
