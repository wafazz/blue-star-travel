<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    protected $guarded = [];

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_achievements')
            ->withPivot('unlocked_at')->withTimestamps();
    }
}
