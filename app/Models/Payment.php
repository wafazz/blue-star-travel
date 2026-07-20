<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'paid_at'         => 'datetime',
        'verified_at'     => 'datetime',
        'gateway_payload' => 'array',
    ];

    const METHODS = [
        'fpx'            => 'FPX',
        'online_banking' => 'Online Banking',
        'slip_upload'    => 'Bank Transfer / Slip',
        'cash'           => 'Cash',
        'card'           => 'Card',
        'other'          => 'Other',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }
}
