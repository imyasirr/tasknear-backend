<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'phone',
        'code',
        'purpose',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isValid(string $code): bool
    {
        return $this->code === $code
            && $this->consumed_at === null
            && $this->expires_at->isFuture();
    }
}
