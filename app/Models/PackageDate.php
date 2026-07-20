<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageDate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'depart_date' => 'date',
        'return_date' => 'date',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function seatsAvailable(): int
    {
        return max(0, (int) $this->seats_total - (int) $this->seats_booked);
    }
}
