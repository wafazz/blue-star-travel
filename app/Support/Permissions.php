<?php

namespace App\Support;

/**
 * Delegatable back-office sections. Each key is an "ability" an `admin` staff member can be granted.
 * super_admin & hq always have every ability (see User::hasAccess). Staff management itself is NOT
 * delegatable — it stays with super_admin & hq.
 */
class Permissions
{
    const GROUPS = [
        'Catalogue' => [
            'packages'  => 'Packages',
            'providers' => 'Providers',
            'customers' => 'Customers',
            'agents'    => 'Agents & MLM network',
        ],
        'Operations' => [
            'bookings' => 'Bookings',
            'payments' => 'Payments',
            'finance'  => 'Finance & Refunds',
        ],
        'Network & Rewards' => [
            'commission' => 'Commission, Withdrawals & Redemptions',
        ],
        'Insights' => [
            'reports' => 'Reports & Analytics',
        ],
        'Engagement' => [
            'marketing' => 'Marketing (Banners / Coupons / Materials / Broadcast)',
            'tickets'   => 'Support Tickets',
        ],
        'Company' => [
            'company' => 'Company Profile & Payment Gateway',
        ],
    ];

    /** key => label across all groups. */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key] ?? ucfirst($key);
    }

    /** Route-name first segment (after `manage.`) → ability. */
    const ROUTE_MAP = [
        'packages'          => 'packages',
        'providers'         => 'providers',
        'customers'         => 'customers',
        'agents'            => 'agents',
        'bookings'          => 'bookings',
        'payments'          => 'payments',
        'finance'           => 'finance',
        'commission'        => 'commission',
        'commission-levels' => 'commission',
        'withdrawals'       => 'commission',
        'redemptions'       => 'commission',
        'reports'           => 'reports',
        'coupons'           => 'marketing',
        'banners'           => 'marketing',
        'materials'         => 'marketing',
        'broadcast'         => 'marketing',
        'tickets'           => 'tickets',
        'company'           => 'company',
        'payment-gateway'   => 'company',
    ];
}
