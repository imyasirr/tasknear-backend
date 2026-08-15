<?php

namespace App\Modules\Workers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerDocument extends Model
{
    protected $fillable = [
        'worker_profile_id',
        'type',
        'path',
        'status',
        'review_note',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class, 'worker_profile_id');
    }
}
