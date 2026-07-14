<?php

namespace App\Console\Commands;

use App\Models\Planning;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckPlanningEcheances extends Command
{
    protected $signature = 'planning:check-echeances';

    protected $description = "Scanne les plannings en attente arrivés à échéance et crée un rappel de notification pour chacun, sans doublon.";

    public function handle(NotificationService $notificationService): int
    {
        $aujourdHui = now()->toDateString();

        $plannings = Planning::where('statut', 'en_attente')
            ->whereDate('date_prevue', '<=', $aujourdHui)
            ->whereDoesntHave('notifications', function ($query) {
                $query->where('type', 'rappel_planning');
            })
            ->get();

        if ($plannings->isEmpty()) {
            $this->info('Aucun planning à échéance nécessitant un rappel.');

            return self::SUCCESS;
        }

        foreach ($plannings as $planning) {
            $notificationService->rappelPlanning($planning);
            $this->info("Rappel créé pour le planning #{$planning->id} ({$planning->titre}).");
        }

        $this->info("{$plannings->count()} rappel(s) créé(s).");

        return self::SUCCESS;
    }
}
