<?php

namespace App\Modules\Marketplace\Models;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatererSkill extends Model
{
    protected $fillable = [
        'caterer_profile_id',
        'category_id',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CatererProfile::class, 'caterer_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
