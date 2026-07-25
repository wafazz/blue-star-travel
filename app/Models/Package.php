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

    const DATE_MODES = [
        'fixed' => 'Scheduled departures only',
        'open'  => 'Open date — traveller picks',
        'both'  => 'Both — departure or own date',
    ];

    /** Departures must be published and a booking must pick one. */
    public function requiresDeparture(): bool
    {
        return $this->date_mode === 'fixed';
    }

    /** The traveller may name their own travel date. */
    public function allowsOpenDate(): bool
    {
        return $this->date_mode !== 'fixed';
    }

    /** Departures a booking may still be placed against. */
    public function bookableDates()
    {
        if ($this->date_mode === 'open') {
            return collect();
        }

        return $this->dates
            ->where('status', 'open')
            ->filter(fn ($d) => $d->seats_total == 0 || $d->seatsAvailable() > 0)
            ->sortBy('depart_date')
            ->values();
    }

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

    public function commissionLevels(): HasMany
    {
        return $this->hasMany(PackageCommissionLevel::class);
    }

    /** Active commission levels for this package, ordered — empty means "use the global default levels". */
    public function activeCommissionLevels()
    {
        return $this->commissionLevels()->where('active', true)->orderBy('level')->get();
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
