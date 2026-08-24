<?php

namespace App\Modules\Venues\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenuePartnerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'bio',
        'city',
        'gstin',
        'upi_vpa',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class, 'partner_user_id', 'user_id');
    }
}
