<?php

namespace App\Modules\Workers\Models;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerSkill extends Model
{
    protected $fillable = [
        'worker_profile_id',
        'category_id',
        'experience_years',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(WorkerProfile::class, 'worker_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
