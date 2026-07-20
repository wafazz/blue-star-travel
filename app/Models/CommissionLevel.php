<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionLevel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'percent' => 'decimal:2',
        'active'  => 'boolean',
    ];

    public static function activeOrdered()
    {
        return static::where('active', true)->orderBy('level')->get();
    }

    public static function depth(): int
    {
        return static::where('active', true)->count();
    }
}
