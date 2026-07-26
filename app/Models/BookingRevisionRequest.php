<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRevisionRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fields'      => 'array',
        'resolved_at' => 'datetime',
    ];

    // The flaggable field registry. Deliberately limited to what an agent can actually
    // change — flagging anything else would deadlock them into phoning admin.
    // APPEND ONLY: a renamed key orphans every stored flag (and, from Phase C, every snapshot).
    const FIELDS = [
        'customer.name'           => ['label' => 'Customer Name',   'group' => 'Customer'],
        'customer.phone'          => ['label' => 'Phone Number',    'group' => 'Customer'],
        'customer.email'          => ['label' => 'Email',           'group' => 'Customer'],
        'customer.ic_passport_no' => ['label' => 'IC / Passport',   'group' => 'Customer'],
        'booking.package_id'      => ['label' => 'Package',         'group' => 'Travel'],
        'booking.package_date_id' => ['label' => 'Departure',       'group' => 'Travel'],
        'booking.travel_date'     => ['label' => 'Travel Date',     'group' => 'Travel'],
        'rooms'                   => ['label' => 'Rooms & Pax',     'group' => 'Travel'],
        'pax'                     => ['label' => 'Passengers',      'group' => 'Travel'],
        'booking.pickup_location' => ['label' => 'Pickup Location', 'group' => 'Pickup'],
        'booking.arrival_time'    => ['label' => 'Arrival Time',    'group' => 'Pickup'],
        'payment.amount'          => ['label' => 'Deposit Paid',    'group' => 'Payment'],
        'payment.method'          => ['label' => 'Payment Method',  'group' => 'Payment'],
        'payment.slip'            => ['label' => 'Payment Receipt', 'group' => 'Payment'],
        'booking.notes'           => ['label' => 'Agent Note',      'group' => 'Note'],
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isFlagged(string $key): bool
    {
        return in_array($key, $this->fields ?? []);
    }

    /** Flagged keys as human labels, for banners and timeline notes. */
    public function fieldLabels(): array
    {
        return array_values(array_map(
            fn ($key) => self::FIELDS[$key]['label'] ?? $key,
            $this->fields ?? []
        ));
    }

    /** FIELDS bucketed by group, for the admin's checkbox modal. */
    public static function fieldsByGroup(): array
    {
        $grouped = [];
        foreach (self::FIELDS as $key => $meta) {
            $grouped[$meta['group']][$key] = $meta['label'];
        }

        return $grouped;
    }
}
