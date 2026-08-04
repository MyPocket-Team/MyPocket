<?php

namespace App\Observers;

use App\Models\Planning;
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
        $this->checkPlanningCompletion($transaction); // Correction Problème 6
    }

    public function updating(Transaction $transaction): void
    {
        // Rien à stocker localement ici : on recalculera la chaîne complète après la mise à jour.
    }

    public function updated(Transaction $transaction): void
    {
        $this->recalculerChaines($transaction->user);
        $this->checkPlanningCompletion($transaction); // Correction Problème 6
    }

    public function deleted(Transaction $transaction): void
    {
        $this->recalculerChaines($transaction->user);
        $this->checkPlanningCompletion($transaction); // Correction Problème 6
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

    /**
     * Correction Problème 6 :
     * Met à jour le statut du planning uniquement si 100% de ses transactions sont réalisées.
     */
    protected function checkPlanningCompletion(Transaction $transaction): void
    {
        if (! $transaction->planned_transaction_id) {
            return;
        }

        $plannedTx = $transaction->plannedTransaction;
        if (! $plannedTx || ! $plannedTx->planning_id) {
            return;
        }

        $planning = Planning::with('plannedTransactions')->find($plannedTx->planning_id);
        if (! $planning) {
            return;
        }

        // Vérifie si TOUTES les transactions du planning sont exécutées
        $allCompleted = $planning->plannedTransactions->every(function ($pt) {
            return $pt->statut === 'realise' || $pt->is_executed;
        });

        $planning->update([
            'statut' => $allCompleted ? 'realise' : 'en_attente',
        ]);
    }

    /**
     * Correction Problème 9 :
     * Gestion des alertes aux seuils de 50% et 90% consommés.
     */
    protected function verifierSeuilAlerte(User $user): void
    {
        $soldeInitial = (float) $user->solde_initial;
        if ($soldeInitial <= 0) {
            return;
        }

        $soldeActuel = (float) $user->solde_actuel;
        $ratioRestant = $soldeActuel / $soldeInitial;

        // 1. Alerte Seuil Critique : 90% consommé (10% ou moins restant)
        if ($ratioRestant <= 0.10) {
            $dejaAlerteCritique = $user->notifications()
                ->where('type', 'seuil_critique_90')
                ->where('lue', false)
                ->exists();

            if (! $dejaAlerteCritique) {
                $this->notificationService->creer(
                    $user->id,
                    "Alerte Solde Critique (90% utilisé)",
                    "Attention : Votre solde actuel est inférieur à 10% de votre solde initial !",
                    "seuil_critique_90"
                );
            }
        }
        // 2. Alerte Seuil Intermédiaire : 50% consommé (entre 10% et 50% restant)
        elseif ($ratioRestant <= 0.50) {
            $dejaAlerte50 = $user->notifications()
                ->where('type', 'seuil_avertissement_50')
                ->where('lue', false)
                ->exists();

            if (! $dejaAlerte50) {
                $this->notificationService->creer(
                    $user->id,
                    "Alerte Solde 50%",
                    "Attention, vous avez consommé la moitié (50%) de votre solde initial.",
                    "seuil_avertissement_50"
                );
            }
        }
    }
}
