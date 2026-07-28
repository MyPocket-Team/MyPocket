<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'code',
        'tentatives',
        'expire_a',
    ];

    /**
     * Obtenir les cast des attributs du modèle.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expire_a' => 'datetime',
            'tentatives' => 'integer',
        ];
    }

    /**
     * Vérifie si le code a dépassé sa durée de validité.
     */
    public function estExpire(): bool
    {
        if (! $this->expire_a) {
            return true;
        }

        return $this->expire_a->isPast();
    }
}

