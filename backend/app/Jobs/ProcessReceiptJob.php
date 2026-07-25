<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\TraitementTranscription;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        protected User $user,
        protected string $cheminFichier,
        protected string $traitementId,
    ) {
    }

    public function handle(GeminiService $gemini): void
    {
        $traitement = TraitementTranscription::where('uuid', $this->traitementId)
            ->where('type', 'recu')
            ->first();

        try {
            $contenu = Storage::disk('local')->get($this->cheminFichier);
            $base64 = base64_encode($contenu);
            $mimeType = Storage::disk('local')->mimeType($this->cheminFichier);

            $donnees = $gemini->extraireDonneesRecu($base64, $mimeType);

            if ($traitement) {
                $traitement->update([
                    'status' => 'termine',
                    'data' => $donnees,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Échec du traitement du reçu', [
                'user_id' => $this->user->id,
                'erreur' => $e->getMessage(),
            ]);

            if ($traitement) {
                $traitement->update([
                    'status' => 'echec',
                    'message' => "L'analyse du reçu a échoué. Vous pouvez réessayer ou saisir manuellement.",
                ]);
            }
        } finally {
            Storage::disk('local')->delete($this->cheminFichier);
        }
    }
}
