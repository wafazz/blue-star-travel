<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gallery'   => 'array',
        'featured'  => 'boolean',
    ];

    const CATEGORIES = [
        'domestic'      => 'Domestic Tours',
        'international'  => 'International Tours',
        'umrah'         => 'Umrah Packages',
        'cruise'        => 'Cruise Packages',
        'free_easy'     => 'Free & Easy',
        'custom'        => 'Custom Tour',
    ];

    const STATUSES = ['draft' => 'Draft', 'active' => 'Active', 'inactive' => 'Inactive'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function pricings(): HasMany
    {
        return $this->hasMany(PackagePricing::class);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(PackageDate::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function defaultPricing(): ?PackagePricing
    {
        return $this->pricings->firstWhere('is_default', true) ?? $this->pricings->first();
    }

    public function fromPrice(): float
    {
        $p = $this->defaultPricing();
        if (! $p) {
            return 0.0;
        }
        return (float) ($p->promo_price ?? $p->adult_price);
    }
}
