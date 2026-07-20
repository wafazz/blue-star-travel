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
        'other'         => 'Other',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
