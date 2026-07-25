<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckSeuilAlerte extends Command
{
    protected $signature = 'alertes:check-seuil';

    protected $description = "Vérifie périodiquement le solde de chaque utilisateur par rapport à son seuil d'alerte, "
        . "pour couvrir les cas où le solde est déjà bas sans qu'une nouvelle transaction ne le déclenche.";

    public function handle(NotificationService $notificationService): int
    {
        $count = 0;

        User::where('solde_initial', '>', 0)->chunk(100, function ($users) use ($notificationService, &$count) {
            foreach ($users as $user) {
                $seuilMontant = $user->solde_initial * ($user->seuil_alerte / 100);

                if ($user->solde_actuel > $seuilMontant) {
                    continue;
                }

                $dejaAlerte = $user->notifications()
                    ->where('type', 'seuil_bas')
                    ->where('lue', false)
                    ->exists();

                if (! $dejaAlerte) {
                    $notificationService->alerteSeuilBas($user);
                    $this->info("Alerte seuil bas créée pour l'utilisateur #{$user->id}.");
                    $count++;
                }
            }
        });

        $this->info("{$count} alerte(s) de seuil bas créée(s) au total.");

        return self::SUCCESS;
    }
}
