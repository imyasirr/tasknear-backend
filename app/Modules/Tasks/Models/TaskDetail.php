<?php

namespace App\Modules\Tasks\Models;

use App\Modules\Marketplace\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDetail extends Model
{
    protected $fillable = [
        'service_request_id',
        'title',
        'description',
        'pickup_address',
        'drop_address',
        'duration_minutes',
        'rate_per_worker_inr',
        'proof_required',
    ];

    protected function casts(): array
    {
        return [
            'proof_required' => 'boolean',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
