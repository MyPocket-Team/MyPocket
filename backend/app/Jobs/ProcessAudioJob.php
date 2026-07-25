<?php

namespace App\Jobs;

use App\Models\TraitementTranscription;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
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
        $traitement = TraitementTranscription::where('uuid', $this->traitementId)
            ->where('type', 'audio')
            ->first();

        $cheminAbsoluOriginal = Storage::disk('local')->path($this->cheminFichier);
        $cheminAbsoluConverti = $cheminAbsoluOriginal.'.converted.wav';

        try {
            // Gemini ne supporte que wav/mp3/aiff/aac/ogg/flac. Les enregistreurs
            // navigateur/mobile produisent généralement du webm/opus (non supporté),
            // donc on convertit systématiquement en WAV mono 16kHz via ffmpeg avant l'appel.
            $conversion = Process::timeout(30)->run([
                config('services.ffmpeg.binary', 'ffmpeg'), '-y', '-i', $cheminAbsoluOriginal,
                '-ar', '16000', '-ac', '1', $cheminAbsoluConverti,
            ]);

            if (! $conversion->successful() || ! file_exists($cheminAbsoluConverti)) {
                throw new \RuntimeException(
                    "Conversion audio (ffmpeg) échouée : {$conversion->errorOutput()}"
                );
            }

            $base64 = base64_encode(file_get_contents($cheminAbsoluConverti));

            $donnees = $gemini->extraireDonneesAudio($base64, 'audio/wav');

            if ($traitement) {
                $traitement->update([
                    'status' => 'termine',
                    'data' => $donnees,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Échec du traitement de l'audio", [
                'user_id' => $this->user->id,
                'erreur' => $e->getMessage(),
            ]);

            if ($traitement) {
                $traitement->update([
                    'status' => 'echec',
                    'message' => "L'analyse audio a échoué. Vous pouvez réessayer ou saisir manuellement.",
                ]);
            }
        } finally {
            Storage::disk('local')->delete($this->cheminFichier);

            if (file_exists($cheminAbsoluConverti)) {
                unlink($cheminAbsoluConverti);
            }
        }
    }
}

