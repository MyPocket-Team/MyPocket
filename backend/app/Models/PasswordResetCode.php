<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    protected $fillable = [
        'email',
        'code',
        'tentatives',
        'expire_a',
    ];

    protected function casts(): array
    {
        return [
            'expire_a' => 'datetime',
        ];
    }

    public function estExpire(): bool
    {
        return $this->expire_a->isPast();
    }
}
