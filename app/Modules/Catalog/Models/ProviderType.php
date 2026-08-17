<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderType extends Model
{
    protected $fillable = [
        'slug',
        'role',
        'match_mode',
        'name',
        'name_hi',
        'description',
        'description_hi',
        'category_slugs',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category_slugs' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, mixed> */
    public function toConfigRow(): array
    {
        return [
            'slug' => $this->slug,
            'role' => $this->role,
            'match_mode' => $this->match_mode,
            'name' => $this->name,
            'name_hi' => $this->name_hi,
            'description' => $this->description,
            'description_hi' => $this->description_hi,
            'category_slugs' => $this->category_slugs ?? [],
            'active' => $this->is_active,
        ];
    }
}
