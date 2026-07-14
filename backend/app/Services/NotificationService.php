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
            'motif' => "Transaction prévue aujourd'hui : {$planning->titre} ({$planning->montant_prevu}).",
            'lue' => false,
            'date_envoi' => now(),
        ]);
    }
}
