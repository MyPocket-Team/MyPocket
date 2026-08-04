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

                // 1. Alerte Seuil Critique : 90% consommé (10% ou moins restant)
                if ($ratioRestant <= 0.10) {
                    $dejaAlerteCritique = $user->notifications()
                        ->where('type', 'seuil_critique_90')
                        ->where('lue', false)
                        ->exists();

                    if (! $dejaAlerteCritique) {
                        $notificationService->creer(
                            $user->id,
                            "Alerte Solde Critique (90% utilisé)",
                            "Attention : Votre solde actuel est inférieur à 10% de votre solde initial !",
                            "seuil_critique_90"
                        );
                        $this->info("Alerte seuil 90% créée pour l'utilisateur #{$user->id}.");
                        $count++;
                    }
                }
                // 2. Alerte Seuil Intermédiaire : 50% consommé (entre 10% et 50% restant)
                elseif ($ratioRestant <= 0.50) {
                    $dejaAlerte50 = $user->notifications()
                        ->where('type', 'seuil_avertissement_50')
                        ->where('lue', false)
                        ->exists();

                    if (! $dejaAlerte50) {
                        $notificationService->creer(
                            $user->id,
                            "Alerte Solde 50%",
                            "Attention, vous avez consommé la moitié (50%) de votre solde initial.",
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
