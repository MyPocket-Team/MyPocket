<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'google_id',
        'role',
        'telephone',
        'activite',
        'photo',
        'seuil_alerte',
        'profil_complete',
        'actif',
        'solde_initial',
        'solde_initial_date',
        'solde_actuel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profil_complete' => 'boolean',
            'actif' => 'boolean',
            'seuil_alerte' => 'float',
            'solde_initial' => 'decimal:2',
            'solde_initial_date' => 'date',
            'solde_actuel' => 'decimal:2',
        ];
    }

    // Relations
    public function categories()
    {
        return $this->hasMany(Categorie::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function plannings()
    {
        return $this->hasMany(Planning::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function chatbotMessages()
    {
        return $this->hasMany(ChatbotMessage::class);
    }

    // Helpers
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function seuilBasAtteint(): bool
    {
        if ($this->solde_initial <= 0) {
            return false;
        }

        $pourcentage = ($this->solde_actuel / $this->solde_initial) * 100;

        return $pourcentage <= $this->seuil_alerte;
    }
}
