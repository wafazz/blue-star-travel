<?php

namespace App\Services\Assistant;

use App\Models\Package;

/**
 * Builds the catalogue the model may recommend from: active packages only, public fields only
 * (REQ-AI-002, REQ-AI-011). Nothing about customers, bookings, providers or agents is included.
 */
class ContextBuilder
{
    public const MAX_PACKAGES = 40;

    public function __construct(private readonly PromptSanitiser $sanitiser) {}

    /** @return array<int, array{id:int, name:string, category:string, destination:string, days:int, price:?string, url:string}> */
    public function packages(): array
    {
        return Package::with('pricings')
            ->where('status', 'active')
            ->latest('featured')
            ->limit(self::MAX_PACKAGES)
            ->get()
            ->map(function (Package $package) {
                $prices = $package->pricings->pluck('adult_price')->filter()->map(fn ($p) => (float) $p);

                return [
                    'id' => $package->id,
                    'name' => $this->sanitiser->sanitiseField($package->title),
                    'category' => $this->sanitiser->sanitiseField(Package::CATEGORIES[$package->category] ?? $package->category),
                    'destination' => $this->sanitiser->sanitiseField($package->destination),
                    'days' => (int) $package->duration_days,
                    'price' => $prices->isNotEmpty() ? 'RM ' . number_format($prices->min(), 0) : null,
                    'url' => route('catalog.show', $package->slug),
                ];
            })
            ->values()
            ->all();
    }
}
