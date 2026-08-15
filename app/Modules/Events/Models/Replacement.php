<?php

namespace App\Modules\Events\Models;

use App\Models\User;
use App\Modules\Marketplace\Models\Assignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Replacement extends Model
{
    protected $fillable = [
        'old_assignment_id',
        'new_assignment_id',
        'reason',
        'created_by',
    ];

    public function oldAssignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'old_assignment_id');
    }

    public function newAssignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'new_assignment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
