<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'passport_expiry' => 'date',
        'dob'             => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $a = isset($parts[0][0]) ? $parts[0][0] : '';
        $b = isset($parts[1][0]) ? $parts[1][0] : '';
        return strtoupper($a . $b) ?: 'C';
    }
}
