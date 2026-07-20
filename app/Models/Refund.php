<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $guarded = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    const METHODS = [
        'bank_transfer' => 'Bank Transfer',
        'fpx_reversal'  => 'FPX Reversal',
        'cash'          => 'Cash',
        'credit_note'   => 'Credit Note',
        'other'         => 'Other',
    ];

    const STATUS_BADGE = [
        'pending'   => 'warning',
        'approved'  => 'info',
        'rejected'  => 'danger',
        'processed' => 'success',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'secondary';
    }
}
