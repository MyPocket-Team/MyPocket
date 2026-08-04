<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessReceiptJob;
use App\Models\TraitementTranscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReceiptController extends Controller
{
    /**
     * Reçoit la photo, la stocke temporairement, et dispatch le Job Gemini.
     * Retourne un traitement_id que le frontend va poller.
     */
    public function upload(Request $request)
    {
        $request->validate([
            // 'image' (basé sur getimagesize) rejette souvent les HEIC/HEIF produits par
            // l'appareil photo des iPhone : on valide plutôt par extension/mime tolérés,
            // et on relève la taille max car les photos prises directement à la caméra
            // sont nettement plus lourdes que des images téléversées depuis la galerie.
            'photo' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:15360'], // 15 Mo max
        ]);

        $traitementId = (string) Str::uuid();
        $chemin = $request->file('photo')->store('recus_temporaires', 'local');

        TraitementTranscription::create([
            'uuid' => $traitementId,
            'user_id' => $request->user()->id,
            'type' => 'recu',
            'status' => 'en_cours',
            'file_path' => $chemin,
        ]);

        // Exécution synchrone : évite toute dépendance à un worker de file d'attente
        // (`queue:work`) qui pourrait ne pas tourner en production, ce qui laissait
        // certains reçus scannés bloqués indéfiniment en statut "en_cours" côté mobile.
        ProcessReceiptJob::dispatchSync($request->user(), $chemin, $traitementId);

        return response()->json([
            'message' => 'Reçu envoyé pour analyse.',
            'traitement_id' => $traitementId,
        ], 202);
    }

    /**
     * Le frontend poll cet endpoint jusqu'à obtenir le statut "termine" ou "echec".
     */
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
