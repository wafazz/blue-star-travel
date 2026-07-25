<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRoom extends Model
{
    protected $guarded = [];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(PackagePricing::class, 'package_pricing_id');
    }

    public function totalPax(): int
    {
        return (int) $this->adults + (int) $this->children + (int) $this->seniors + (int) $this->infants;
    }
}
