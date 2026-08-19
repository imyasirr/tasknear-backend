<?php

namespace App\Modules\Money\Models;

use App\Models\User;
use App\Modules\Marketplace\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'service_request_id',
        'payer_id',
        'amount_inr',
        'labor_inr',
        'commission_inr',
        'commission_bps',
        'fee_waived',
        'subscription_id',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'fee_waived' => 'boolean',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }
}
