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
    /**
     * Lie ce planning à une transaction réelle. Le statut ne passe à "realise" que si
     * le planning n'a aucune transaction planifiée en attente (sinon on attendra que
     * PlannedTransactionController::confirmer/destroy fasse passer le statut une fois
     * que 100% des transactions planifiées sont réalisées).
     */
    public function lierATransaction(Transaction $transaction): void
    {
        $update = ['transaction_id' => $transaction->id];

        $aDesTransactionsPlanifiees = $this->plannedTransactions()->exists();
        $toutesRealisees = $aDesTransactionsPlanifiees
            && ! $this->plannedTransactions()->where('statut', '!=', 'realise')->exists();

        if (! $aDesTransactionsPlanifiees || $toutesRealisees) {
            $update['statut'] = 'realise';
        }

        $this->update($update);
    }
}
