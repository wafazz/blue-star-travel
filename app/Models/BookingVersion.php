<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingVersion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'changes' => 'array',
    ];

    const REASONS = [
        'initial'    => 'Original submission',
        'revision'   => 'Agent revision',
        'amendment'  => 'Approved amendment',
        'admin_edit' => 'Edited by staff',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisionRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRevisionRequest::class, 'revision_request_id');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
