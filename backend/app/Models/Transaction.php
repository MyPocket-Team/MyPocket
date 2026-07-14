<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'categorie_id',
        'montant',
        'description',
        'date_transaction',
        'solde_avant',
        'solde_apres',
        'type',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'date_transaction' => 'datetime',
            'montant' => 'decimal:2',
            'solde_avant' => 'decimal:2',
            'solde_apres' => 'decimal:2',
        ];
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    public function planning()
    {
        return $this->hasOne(Planning::class);
    }

    // Scopes
    public function scopeRevenus($query)
    {
        return $query->where('type', 'revenu');
    }

    public function scopeDepenses($query)
    {
        return $query->where('type', 'depense');
    }

    public function scopeSourceIA($query)
    {
        return $query->whereIn('source', ['ia_recu', 'ia_audio']);
    }
}
