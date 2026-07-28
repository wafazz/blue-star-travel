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

    /** "Bank Transfer / Slip" blows the history table past the phone frame. */
    public function methodShort(): string
    {
        return [
            'fpx'            => 'FPX',
            'online_banking' => 'Online',
            'slip_upload'    => 'Bank Transfer',
            'cash'           => 'Cash',
            'card'           => 'Card',
        ][$this->method] ?? 'Other';
    }

    /** One colour per method, so a history row is readable at a glance. */
    public function methodBadge(): string
    {
        return [
            'fpx'            => 'info',
            'online_banking' => 'primary',
            'slip_upload'    => 'dark',
            'cash'           => 'success',
            'card'           => 'warning',
        ][$this->method] ?? 'secondary';
    }

    public function statusBadge(): string
    {
        return ['verified' => 'success', 'rejected' => 'danger'][$this->status] ?? 'warning';
    }

    /** What the agent sees on a row: "Recorded" until staff have actually checked it. */
    public function statusLabel(): string
    {
        return ['verified' => 'Verified', 'rejected' => 'Rejected'][$this->status] ?? 'Recorded';
    }
}
