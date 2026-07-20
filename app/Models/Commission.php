<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $guarded = [];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'percent'     => 'decimal:2',
        'amount'      => 'decimal:2',
        'is_orphan'   => 'boolean',
        'approved_at' => 'datetime',
    ];

    const STATUS_BADGE = [
        'pending'  => 'warning',
        'approved' => 'info',
        'paid'     => 'success',
        'reversed' => 'danger',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function earner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'earner_id');
    }

    public function sourceAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_agent_id');
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'secondary';
    }
}
