<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageCommissionLevel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'adult_value'  => 'decimal:2',
        'child_value'  => 'decimal:2',
        'senior_value' => 'decimal:2',
        'infant_value' => 'decimal:2',
        'is_hq'        => 'boolean',
        'active'       => 'boolean',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** Value configured for a given pax type (adult|child|senior|infant). */
    public function valueFor(string $paxType): float
    {
        return (float) ($this->{$paxType . '_value'} ?? 0);
    }
}
