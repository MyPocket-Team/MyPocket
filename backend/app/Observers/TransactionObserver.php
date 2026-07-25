<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;

class TransactionObserver
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    public function creating(Transaction $transaction): void
    {
        $precedente = $this->getPrecedente($transaction);
        $soldeAvant = $precedente
            ? $precedente->solde_apres
            : $transaction->user->solde_initial;

        $transaction->solde_avant = $soldeAvant;
        $transaction->solde_apres = $transaction->type === 'revenu'
            ? $soldeAvant + $transaction->montant
            : $soldeAvant - $transaction->montant;
    }

    public function created(Transaction $transaction): void
    {
        $this->recalculerChaines($transaction->user);
    }

    public function updating(Transaction $transaction): void
    {
        // Rien à stocker localement ici : on recalculera la chaîne complète après la mise à jour.
    }

    public function updated(Transaction $transaction): void
    {
        $this->recalculerChaines($transaction->user);
    }

    public function deleted(Transaction $transaction): void
    {
        $this->recalculerChaines($transaction->user);
    }

    protected function getPrecedente(Transaction $transaction): ?Transaction
    {
        return Transaction::where('user_id', $transaction->user_id)
            ->where(function ($query) use ($transaction) {
                $query->where('date_transaction', '<', $transaction->date_transaction)
                    ->orWhere(function ($query) use ($transaction) {
                        $query->where('date_transaction', $transaction->date_transaction)
                            ->when($transaction->id, function ($query) use ($transaction) {
                                $query->where('id', '<', $transaction->id);
                            });
                    });
            })
            ->orderByDesc('date_transaction')
            ->orderByDesc('id')
            ->first();
    }

    protected function recalculerChaines(User $user): void
    {
        $transactions = $user->transactions()
            ->orderBy('date_transaction')
            ->orderBy('id')
            ->get();

        $solde = $user->solde_initial;

        Transaction::withoutEvents(function () use ($transactions, &$solde) {
            foreach ($transactions as $transaction) {
                $soldeAvant = $solde;
                $soldeApres = $transaction->type === 'revenu'
                    ? $soldeAvant + $transaction->montant
                    : $soldeAvant - $transaction->montant;

                $transaction->update([
                    'solde_avant' => $soldeAvant,
                    'solde_apres' => $soldeApres,
                ]);

                $solde = $soldeApres;
            }
        });

        $user->update(['solde_actuel' => $solde]);

        $this->verifierSeuilAlerte($user->fresh());
    }

    protected function verifierSeuilAlerte(User $user): void
    {
        if ($user->solde_initial <= 0) {
            return;
        }

        $seuilMontant = $user->solde_initial * ($user->seuil_alerte / 100);

        // Solde encore au-dessus du seuil : rien à signaler.
        if ($user->solde_actuel > $seuilMontant) {
            return;
        }

        // Évite de spammer une notification à chaque transaction tant que
        // l'utilisateur n'a pas encore lu la précédente alerte de seuil bas.
        $dejaAlerte = $user->notifications()
            ->where('type', 'seuil_bas')
            ->where('lue', false)
            ->exists();

        if (! $dejaAlerte) {
            $this->notificationService->alerteSeuilBas($user);
        }
    }
}
