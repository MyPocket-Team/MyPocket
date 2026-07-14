<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessAudioJob implements ShouldQueue
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
        $cacheKey = "traitement_audio:{$this->traitementId}";

        try {
            $contenu = Storage::disk('local')->get($this->cheminFichier);
            $base64 = base64_encode($contenu);
            $mimeType = Storage::disk('local')->mimeType($this->cheminFichier);

            $donnees = $gemini->extraireDonneesAudio($base64, $mimeType);

            Cache::put($cacheKey, [
                'statut' => 'termine',
                'donnees' => $donnees,
            ], now()->addMinutes(30));
        } catch (\Throwable $e) {
            Log::error("Échec du traitement de l'audio", [
                'user_id' => $this->user->id,
                'erreur' => $e->getMessage(),
            ]);

            Cache::put($cacheKey, [
                'statut' => 'echec',
                'message' => "L'analyse audio a échoué. Vous pouvez réessayer ou saisir manuellement.",
            ], now()->addMinutes(30));
        } finally {
            Storage::disk('local')->delete($this->cheminFichier);
        }
    }
}
