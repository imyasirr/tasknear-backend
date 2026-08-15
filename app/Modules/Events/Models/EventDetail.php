<?php

namespace App\Modules\Events\Models;

use App\Modules\Marketplace\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventDetail extends Model
{
    protected $fillable = [
        'service_request_id',
        'title',
        'venue_name',
        'guest_count',
        'dress_code',
        'meal_included',
    ];

    protected function casts(): array
    {
        return [
            'meal_included' => 'boolean',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(EventShift::class);
    }
}
