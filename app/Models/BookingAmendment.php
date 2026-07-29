<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAmendment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requested_date'  => 'date',
        'reviewed_at'     => 'datetime',
        'is_postponement' => 'boolean',
    ];

    const TYPES = [
        'travel_date' => 'Change Date',
        'pickup'      => 'Change Pickup Details',
        'other'       => 'Other (handled manually)',
    ];

    const STATUS_BADGE = [
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function packageDate(): BelongsTo
    {
        return $this->belongsTo(PackageDate::class, 'requested_package_date_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'secondary';
    }

    /** `other` is recorded for staff to action by hand — approval applies nothing. */
    public function isAutoApplied(): bool
    {
        return in_array($this->type, ['travel_date', 'pickup']);
    }

    /** A date change the customer has not dated yet. Approval parks the trip, not cancels it. */
    public function isPostponement(): bool
    {
        return $this->type === 'travel_date' && $this->is_postponement;
    }

    /** What the agent asked for, for the "To" column on both review screens. */
    public function requestedLabel(): string
    {
        if ($this->isPostponement()) {
            return 'Postponed — no new date yet';
        }

        return optional($this->packageDate?->depart_date)->format('d M Y')
            ?? optional($this->requested_date)->format('d M Y')
            ?? $this->requested_pickup_location
            ?? '—';
    }
}
