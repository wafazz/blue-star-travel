<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_reply_at' => 'datetime',
    ];

    const CATEGORIES = [
        'booking'    => 'Booking',
        'payment'    => 'Payment',
        'commission' => 'Commission',
        'technical'  => 'Technical',
        'complaint'  => 'Complaint',
        'other'      => 'Other',
    ];

    const STATUS_BADGE = [
        'open'     => 'primary',
        'pending'  => 'warning',
        'resolved' => 'success',
        'closed'   => 'secondary',
    ];

    const PRIORITY_BADGE = [
        'low'    => 'secondary',
        'normal' => 'info',
        'high'   => 'warning',
        'urgent' => 'danger',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest();
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'secondary';
    }

    public function priorityBadge(): string
    {
        return self::PRIORITY_BADGE[$this->priority] ?? 'secondary';
    }
}
