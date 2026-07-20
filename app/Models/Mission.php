<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    protected $guarded = [];

    protected $casts = [
        'auto'   => 'boolean',
        'active' => 'boolean',
    ];

    public function completions(): HasMany
    {
        return $this->hasMany(MissionCompletion::class);
    }
}
