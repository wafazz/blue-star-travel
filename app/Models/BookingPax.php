<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPax extends Model
{
    protected $table = 'booking_pax';

    protected $guarded = [];

    protected $casts = [
        'dob'     => 'date',
        'is_lead' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
