<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Planning;
use App\Models\User;

class NotificationService
{
    public function alerteSeuilBas(User $user): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'seuil_bas',
            'titre' => 'Alerte : solde bas',
            'motif' => "Votre solde a atteint {$user->solde_actuel} (seuil d'alerte : {$user->seuil_alerte}% du solde initial).",
            'lue' => false,
            'date_envoi' => now(),
        ]);
    }

    public function rappelPlanning(Planning $planning): Notification
    {
        return Notification::create([
            'user_id' => $planning->user_id,
            'planning_id' => $planning->id,
            'type' => 'rappel_planning',
            'titre' => "Rappel : {$planning->titre}",
            'motif' => "Planning à suivre : {$planning->titre}.",
            'lue' => false,
            'date_envoi' => now(),
        ]);
    }

    public function rappelTransactionPlanifiee(\App\Models\PlannedTransaction $plannedTx, int $milestone): Notification
    {
        $echeanceStr = $plannedTx->date_echeance->format('d/m/Y');
        
        $titre = match($milestone) {
            5 => "Rappel J-5 : " . ($plannedTx->description ?: "Transaction planifiée"),
            2 => "Rappel J-2 : " . ($plannedTx->description ?: "Transaction planifiée"),
            0 => "Échéance à terme : " . ($plannedTx->description ?: "Transaction planifiée"),
            default => "Rappel Échéance : " . ($plannedTx->description ?: "Transaction planifiée"),
        };

        $motif = match($milestone) {
            5 => "Votre transaction planifiée de {$plannedTx->montant} FCFA arrive à échéance dans 5 jours ({$echeanceStr}).",
            2 => "Votre transaction planifiée de {$plannedTx->montant} FCFA arrive à échéance dans 2 jours ({$echeanceStr}).",
            0 => "Votre transaction planifiée de {$plannedTx->montant} FCFA est arrivée à échéance aujourd'hui ({$echeanceStr}). Veuillez la valider.",
            default => "Votre transaction planifiée de {$plannedTx->montant} FCFA est prévue pour le {$echeanceStr}.",
        };

        return Notification::create([
            'user_id' => $plannedTx->user_id,
            'planning_id' => $plannedTx->planning_id,
            'planned_transaction_id' => $plannedTx->id,
            'milestone' => $milestone,
            'type' => 'rappel_planning',
            'titre' => $titre,
            'motif' => $motif,
            'lue' => false,
            'date_envoi' => now(),
        ]);
    }

}
