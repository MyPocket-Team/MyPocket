<?php

namespace App\Console\Commands;

use App\Models\Planning;
use App\Models\PlannedTransaction;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckPlanningEcheances extends Command
{
    protected $signature = 'planning:check-echeances';

    protected $description = "Scanne les plannings et transactions planifiées en attente pour envoyer les rappels d'échéances.";

    public function handle(NotificationService $notificationService): int
    {
        $aujourdHui = now()->toDateString();
        $notificationCount = 0;

        // 1. Rappels pour les anciens Plannings
        $plannings = Planning::where('statut', 'en_attente')
            ->whereNotNull('date_prevue')
            ->whereDate('date_prevue', '<=', $aujourdHui)
            ->whereDoesntHave('notifications', function ($query) {
                $query->where('type', 'rappel_planning')->whereNull('planned_transaction_id');
            })
            ->get();

        foreach ($plannings as $planning) {
            $notificationService->rappelPlanning($planning);
            $this->info("Rappel créé pour le planning #{$planning->id} ({$planning->titre}).");
            $notificationCount++;
        }

        // 2. Rappels pour les Transactions Planifiées (J-5, J-2, J-0)
        $plannedTxs = PlannedTransaction::where('statut', 'en_attente')->get();

        foreach ($plannedTxs as $plannedTx) {
            $diffInDays = now()->startOfDay()->diffInDays($plannedTx->date_echeance->startOfDay(), false);

            $milestone = null;
            if ($diffInDays === 5) {
                $milestone = 5;
            } elseif ($diffInDays === 2) {
                $milestone = 2;
            } elseif ($diffInDays <= 0) {
                $milestone = 0;
            }

            if ($milestone !== null) {
                // Vérifie si un rappel pour ce milestone existe déjà
                $exists = $plannedTx->notifications()
                    ->where('milestone', $milestone)
                    ->exists();

                if (!$exists) {
                    $notificationService->rappelTransactionPlanifiee($plannedTx, $milestone);
                    $this->info("Rappel J-{$milestone} créé pour la transaction planifiée #{$plannedTx->id} ({$plannedTx->description}).");
                    $notificationCount++;
                }
            }
        }

        $this->info("{$notificationCount} rappel(s) créé(s) au total.");
        return self::SUCCESS;
    }
}

