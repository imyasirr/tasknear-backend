<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorOffer extends Model
{
    public const COMMITTED = ['accepted'];

    protected $fillable = [
        'service_request_id',
        'caterer_user_id',
        'status',
        'assigned_by',
        'invited_at',
        'urgent_until',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'urgent_until' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function caterer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caterer_user_id');
    }
}
