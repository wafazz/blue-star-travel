<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $guarded = [];

    const TYPES = [
        'hotel'          => 'Hotel',
        'airline'        => 'Airline',
        'transport'      => 'Transport',
        'tour_guide'     => 'Tour Guide',
        'attraction'     => 'Attraction',
        'local_operator' => 'Local Operator',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
