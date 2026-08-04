<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAudioJob;
use App\Models\TraitementTranscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AudioController extends Controller
{
    /**
     * Extensions et MIME détectés tolérés pour un enregistrement audio.
     * Les enregistreurs mobiles (iOS/Android) et navigateurs produisent des conteneurs dont le
     * MIME détecté par le serveur (via libmagic) varie beaucoup pour un même format déclaré
     * (ex. un .aac brut est parfois détecté "audio/x-hx-aac-adts", un .m4a parfois "audio/mp4").
     * On accepte donc le fichier si SOIT son extension SOIT son MIME détecté est reconnu,
     * plutôt que d'exiger une correspondance stricte entre les deux.
     */
    protected const EXTENSIONS_AUTORISEES = ['mp3', 'wav', 'm4a', 'ogg', 'aac', '3gp', '3gpp', 'caf', 'amr', 'webm'];

    protected const MIMES_AUTORISES = [
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/wave',
        'audio/m4a', 'audio/x-m4a', 'audio/mp4', 'video/mp4',
        'audio/aac', 'audio/x-aac', 'audio/x-hx-aac-adts',
        'audio/3gpp', 'audio/3gpp2', 'video/3gpp',
        'audio/amr', 'audio/x-caf', 'audio/ogg', 'audio/webm',
    ];

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'audio' => ['required', 'file', 'max:10240'], // 10 Mo max
        ]);

        $validator->after(function ($validator) use ($request) {
            $fichier = $request->file('audio');

            if (! $fichier) {
                return;
            }

            $extension = strtolower((string) $fichier->getClientOriginalExtension());
            $mime = (string) $fichier->getMimeType();

            $extensionOk = in_array($extension, self::EXTENSIONS_AUTORISEES, true);
            $mimeOk = in_array($mime, self::MIMES_AUTORISES, true);

            if (! $extensionOk && ! $mimeOk) {
                $validator->errors()->add(
                    'audio',
                    'Le fichier audio doit être dans un format supporté : '.implode(', ', self::EXTENSIONS_AUTORISEES).'.'
                );
            }
        });

        $validator->validate();

        $traitementId = (string) Str::uuid();
        $chemin = $request->file('audio')->store('audios_temporaires', 'local');

        TraitementTranscription::create([
            'uuid' => $traitementId,
            'user_id' => $request->user()->id,
            'type' => 'audio',
            'status' => 'en_cours',
            'file_path' => $chemin,
        ]);

        // Exécution synchrone : évite toute dépendance à un worker de file d'attente
        // (`queue:work`) qui pourrait ne pas tourner en production.
        ProcessAudioJob::dispatchSync($request->user(), $chemin, $traitementId);

        return response()->json([
            'message' => 'Audio envoyé pour analyse.',
            'traitement_id' => $traitementId,
        ], 202);
    }

    public function statut(Request $request, string $traitementId)
    {
        $traitement = TraitementTranscription::where('uuid', $traitementId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $traitement) {
            return response()->json([
                'message' => 'Traitement introuvable.',
            ], 404);
        }

        return response()->json([
            'status' => $traitement->status,
            'data' => $traitement->data,
            'message' => $traitement->message,
        ]);
    }
}
