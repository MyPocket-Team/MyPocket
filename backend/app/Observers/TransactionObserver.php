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

    /**
     * Alerte de solde bas : déclenchée dès que le solde actuel atteint (ou descend sous)
     * 90% du solde initial, puis une seconde fois à 50%. Les deux seuils sont vérifiés
     * indépendamment (pas de "elseif") afin que les deux notifications finissent par
     * apparaître au fil de la baisse du solde, chacune une seule fois tant qu'elle
     * n'a pas été marquée comme lue.
     */
    protected function verifierSeuilAlerte(User $user): void
    {
        $soldeInitial = (float) $user->solde_initial;
        if ($soldeInitial <= 0) {
            return;
        }

        $soldeActuel = (float) $user->solde_actuel;
        $ratioRestant = $soldeActuel / $soldeInitial;

        // 1. Alerte à 90% du solde initial
        if ($ratioRestant <= 0.90) {
            $dejaAlerte90 = $user->notifications()
                ->where('type', 'seuil_avertissement_90')
                ->where('lue', false)
                ->exists();

            if (! $dejaAlerte90) {
                $this->notificationService->creer(
                    $user->id,
                    "Alerte Solde (90%)",
                    "Votre solde actuel est descendu à 90% (ou moins) de votre solde initial.",
                    "seuil_avertissement_90"
                );
            }
        }

        // 2. Alerte à 50% du solde initial
        if ($ratioRestant <= 0.50) {
            $dejaAlerte50 = $user->notifications()
                ->where('type', 'seuil_avertissement_50')
                ->where('lue', false)
                ->exists();

            if (! $dejaAlerte50) {
                $this->notificationService->creer(
                    $user->id,
                    "Alerte Solde Critique (50%)",
                    "Attention, votre solde actuel est descendu à 50% (ou moins) de votre solde initial.",
                    "seuil_avertissement_50"
                );
            }
        }
    }
}
