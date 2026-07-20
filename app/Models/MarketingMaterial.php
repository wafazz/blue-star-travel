<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingMaterial extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    const CATEGORIES = [
        'poster'   => 'Poster',
        'video'    => 'Video',
        'brochure' => 'Brochure',
        'social'   => 'Social Media',
        'other'    => 'Other',
    ];

    const CATEGORY_ICON = [
        'poster'   => '🖼️',
        'video'    => '🎬',
        'brochure' => '📄',
        'social'   => '📱',
        'other'    => '📎',
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function icon(): string
    {
        return self::CATEGORY_ICON[$this->category] ?? '📎';
    }
}
