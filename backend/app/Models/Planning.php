<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Planning extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'titre',
        'description',
        'date_prevue',
        'statut',
    ];

    protected function casts(): array
    {
        return [];
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

   
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function plannedTransactions()
    {
        return $this->hasMany(PlannedTransaction::class);
    }


    // Scopes
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeAEcheance($query, $date)
    {
        return $query->where('statut', 'en_attente')
            ->whereDate('date_prevue', $date);
    }

    // Helpers
    public function lierATransaction(Transaction $transaction): void
    {
        $this->update([
            'transaction_id' => $transaction->id,
            'statut' => 'realise',
        ]);
    }
}
