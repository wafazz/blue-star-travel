<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    protected $guarded = [];

    protected $casts = [
        'travel_date'           => 'date',
        'submitted_at'          => 'datetime',
        'sent_to_provider_at'   => 'datetime',
        'provider_responded_at' => 'datetime',
        'confirmed_at'          => 'datetime',
        'rejected_at'           => 'datetime',
        'cancelled_at'          => 'datetime',
        'completed_at'          => 'datetime',
    ];

    const STATUSES = [
        'draft'                         => 'Draft',
        'pending_payment'               => 'Pending Payment',
        'pending_verification'          => 'Pending Verification',
        'waiting_provider_confirmation' => 'Waiting Provider',
        'confirmed'                     => 'Confirmed',
        'rejected'                      => 'Rejected',
        'cancelled'                     => 'Cancelled',
        'completed'                     => 'Completed',
        'refunded'                      => 'Refunded',
    ];

    const STATUS_BADGE = [
        'draft'                         => 'secondary',
        'pending_payment'               => 'warning',
        'pending_verification'          => 'info',
        'waiting_provider_confirmation' => 'primary',
        'confirmed'                     => 'success',
        'rejected'                      => 'danger',
        'cancelled'                     => 'secondary',
        'completed'                     => 'success',
        'refunded'                      => 'dark',
    ];

    const TYPES = [
        'manual'    => 'Manual',
        'online'    => 'Online',
        'group'     => 'Group',
        'family'    => 'Family',
        'corporate' => 'Corporate',
        'walk_in'   => 'Walk-in',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function packageDate(): BelongsTo
    {
        return $this->belongsTo(PackageDate::class);
    }

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(PackagePricing::class, 'package_pricing_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function pax(): HasMany
    {
        return $this->hasMany(BookingPax::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(BookingTimeline::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BookingDocument::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->latest();
    }

    public function refundedAmount(): float
    {
        return (float) $this->refunds()->where('status', 'processed')->sum('amount');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'secondary';
    }

    public function balance(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balance() <= 0;
    }

    public function document(string $type): ?BookingDocument
    {
        return $this->documents->firstWhere('type', $type);
    }
}
