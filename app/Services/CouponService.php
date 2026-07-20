<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validate a coupon code against a subtotal and return the coupon.
     * Throws a ValidationException (keyed 'coupon_code') on any failure.
     */
    public function validate(string $code, float $subtotal): Coupon
    {
        $coupon = Coupon::whereRaw('LOWER(code) = ?', [strtolower(trim($code))])->first();

        $fail = fn ($msg) => throw ValidationException::withMessages(['coupon_code' => $msg]);

        if (! $coupon || ! $coupon->active) {
            $fail('Invalid or inactive coupon code.');
        }
        if ($coupon->isExhausted()) {
            $fail('This coupon has reached its usage limit.');
        }
        $today = today();
        if ($coupon->starts_at && $coupon->starts_at->gt($today)) {
            $fail('This coupon is not active yet.');
        }
        if ($coupon->expires_at && $coupon->expires_at->lt($today)) {
            $fail('This coupon has expired.');
        }
        if ($subtotal < (float) $coupon->min_spend) {
            $fail('Minimum spend of RM ' . number_format($coupon->min_spend, 2) . ' required.');
        }

        return $coupon;
    }

    public function redeem(Coupon $coupon): void
    {
        $coupon->increment('used_count');
    }
}
