<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDocument extends Model
{
    protected $guarded = [];

    const TYPES = [
        'invoice'       => 'Invoice',
        'voucher'       => 'Travel Voucher',
        'receipt'       => 'Receipt',
        'flight_ticket' => 'Flight Ticket',
        'hotel_voucher' => 'Hotel Voucher',
        'visa'          => 'Visa Document',
        'insurance'     => 'Travel Insurance',
        'payment_slip'  => 'Payment Slip',
        'confirmation'  => 'Provider Confirmation',
        'resort_invoice' => 'Resort Invoice',
        'other'         => 'Other',
    ];

    /**
     * Staff-only paperwork. The resort invoice carries what Blue Star pays the resort —
     * an agent seeing it sees the company's margin, so it never leaves /manage.
     */
    const INTERNAL_TYPES = ['resort_invoice'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isInternal(): bool
    {
        return in_array($this->type, self::INTERNAL_TYPES, true);
    }

    public function scopeShareable($query)
    {
        return $query->whereNotIn('type', self::INTERNAL_TYPES);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
