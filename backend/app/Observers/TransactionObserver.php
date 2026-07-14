<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\NotificationService;

class TransactionObserver
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Avant la création : calcule solde_avant / solde_apres
     * en fonction du solde_actuel de l'utilisateur au moment T.
     */
    public function creating(Transaction $transaction): void
    {
        $user = $transaction->user;

        $soldeAvant = $user->solde_actuel;

        $soldeApres = $transaction->type === 'revenu'
            ? $soldeAvant + $transaction->montant
            : $soldeAvant - $transaction->montant;

        $transaction->solde_avant = $soldeAvant;
        $transaction->solde_apres = $soldeApres;
    }

    /**
     * Après la création : met à jour le solde_actuel du user
     * et déclenche l'alerte si le seuil bas est atteint.
     */
    public function created(Transaction $transaction): void
    {
        $user = $transaction->user;

        $user->update(['solde_actuel' => $transaction->solde_apres]);

        if ($user->fresh()->seuilBasAtteint()) {
            $this->notificationService->alerteSeuilBas($user);
        }
    }

    /**
     * Si une transaction est supprimée, on recrédite/débite le solde
     * pour rester cohérent (cas: erreur de saisie supprimée par l'utilisateur).
     */
    public function deleted(Transaction $transaction): void
    {
        $user = $transaction->user;

        $ajustement = $transaction->type === 'revenu'
            ? -$transaction->montant
            : $transaction->montant;

        $user->update(['solde_actuel' => $user->solde_actuel + $ajustement]);
    }
}
