<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active'     => 'boolean',
        'starts_at'  => 'date',
        'ends_at'    => 'date',
    ];

    public function scopeLive($query, string $placement)
    {
        $today = today();

        return $query->where('active', true)
            ->whereIn('placement', [$placement, 'both'])
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->orderBy('sort');
    }
}
