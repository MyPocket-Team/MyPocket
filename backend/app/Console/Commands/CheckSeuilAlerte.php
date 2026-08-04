<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckSeuilAlerte extends Command
{
    protected $signature = 'alertes:check-seuil';

    protected $description = "Vérifie périodiquement le solde de chaque utilisateur par rapport aux seuils d'alerte (50% et 90% consommés), "
        . "pour couvrir les cas où le solde est bas sans qu'une nouvelle transaction ne le déclenche.";

    public function handle(NotificationService $notificationService): int
    {
        $count = 0;

        User::where('solde_initial', '>', 0)->chunk(100, function ($users) use ($notificationService, &$count) {
            foreach ($users as $user) {
                $soldeInitial = (float) $user->solde_initial;
                $soldeActuel = (float) $user->solde_actuel;

                // Ratio du solde restant par rapport au solde initial
                $ratioRestant = $soldeActuel / $soldeInitial;

                // 1. Alerte à 90% du solde initial
                if ($ratioRestant <= 0.90) {
                    $dejaAlerte90 = $user->notifications()
                        ->where('type', 'seuil_avertissement_90')
                        ->where('lue', false)
                        ->exists();

                    if (! $dejaAlerte90) {
                        $notificationService->creer(
                            $user->id,
                            "Alerte Solde (90%)",
                            "Votre solde actuel est descendu à 90% (ou moins) de votre solde initial.",
                            "seuil_avertissement_90"
                        );
                        $this->info("Alerte seuil 90% créée pour l'utilisateur #{$user->id}.");
                        $count++;
                    }
                }

                // 2. Alerte à 50% du solde initial (indépendante de la précédente)
                if ($ratioRestant <= 0.50) {
                    $dejaAlerte50 = $user->notifications()
                        ->where('type', 'seuil_avertissement_50')
                        ->where('lue', false)
                        ->exists();

                    if (! $dejaAlerte50) {
                        $notificationService->creer(
                            $user->id,
                            "Alerte Solde Critique (50%)",
                            "Attention, votre solde actuel est descendu à 50% (ou moins) de votre solde initial.",
                            "seuil_avertissement_50"
                        );
                        $this->info("Alerte seuil 50% créée pour l'utilisateur #{$user->id}.");
                        $count++;
                    }
                }
            }
        });

        $this->info("{$count} alerte(s) de seuil créée(s) au total.");

        return self::SUCCESS;
    }
}
