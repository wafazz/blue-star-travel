<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redemption extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cash_value'   => 'decimal:2',
        'fulfilled_at' => 'datetime',
    ];

    const TYPES = [
        'cash'           => 'Cash',
        'travel_voucher' => 'Travel Voucher',
        'shopping'       => 'Shopping Voucher',
        'merchandise'    => 'Merchandise',
        'commission'     => 'Extra Commission',
        'free_trip'      => 'Free Trip',
        'hotel'          => 'Hotel Voucher',
    ];

    const STATUS_BADGE = [
        'pending'   => 'warning',
        'approved'  => 'info',
        'fulfilled' => 'success',
        'rejected'  => 'danger',
    ];

    // Redeem catalog: type => [points cost, cash value RM, icon]
    const CATALOG = [
        'cash'           => [1000, 50.00, '💵'],
        'travel_voucher' => [1500, 80.00, '🎟️'],
        'shopping'       => [1200, 60.00, '🛍️'],
        'merchandise'    => [800, 40.00, '🎽'],
        'commission'     => [2000, 100.00, '💰'],
        'free_trip'      => [10000, 600.00, '✈️'],
        'hotel'          => [2500, 150.00, '🏨'],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'secondary';
    }
}
