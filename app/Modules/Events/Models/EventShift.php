<?php

namespace App\Modules\Events\Models;

use App\Modules\Catalog\Models\Category;
use App\Modules\Marketplace\Models\Assignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventShift extends Model
{
    protected $fillable = [
        'event_detail_id',
        'category_id',
        'headcount',
        'start_at',
        'end_at',
        'rate_per_worker_inr',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function eventDetail(): BelongsTo
    {
        return $this->belongsTo(EventDetail::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function filledCount(): int
    {
        return $this->assignments()
            ->whereIn('status', Assignment::COMMITTED)
            ->count();
    }

    public function openSlots(): int
    {
        return max(0, $this->headcount - $this->filledCount());
    }
}
