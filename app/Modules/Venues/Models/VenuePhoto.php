<?php

namespace App\Modules\Venues\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePhoto extends Model
{
    protected $fillable = [
        'venue_id',
        'path',
        'media_type',
        'sort_order',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function isImage(): bool
    {
        return ! $this->isVideo();
    }

    public function url(): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', $this->path), '/');
    }

    /** @return array<string, mixed> */
    public function toMediaRow(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'media_type' => $this->media_type ?: 'image',
            'sort_order' => $this->sort_order,
        ];
    }
}
