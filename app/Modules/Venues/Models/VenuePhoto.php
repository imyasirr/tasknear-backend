<?php

namespace App\Modules\Venues\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePhoto extends Model
{
    protected $fillable = [
        'venue_id',
        'path',
        'sort_order',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function url(): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', $this->path), '/');
    }
}
