<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Read one dotted key out of the staged payload, e.g. 'customer.name'. */
    public function value(string $key, $default = null)
    {
        return data_get($this->payload, $key, $default);
    }

    /** True when staff moved the booking on while this edit was open. */
    public function isStale(): bool
    {
        return $this->base_version !== null
            && $this->base_version !== $this->booking?->versions()->max('version');
    }
}
