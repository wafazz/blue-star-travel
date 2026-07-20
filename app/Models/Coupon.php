<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = [];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_spend'      => 'decimal:2',
        'max_discount'   => 'decimal:2',
        'active'         => 'boolean',
        'starts_at'      => 'date',
        'expires_at'     => 'date',
    ];

    public function discountFor(float $subtotal): float
    {
        $raw = $this->discount_type === 'percent'
            ? $subtotal * (float) $this->discount_value / 100
            : (float) $this->discount_value;

        if ($this->max_discount) {
            $raw = min($raw, (float) $this->max_discount);
        }

        return round(min($raw, $subtotal), 2);
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }
}
