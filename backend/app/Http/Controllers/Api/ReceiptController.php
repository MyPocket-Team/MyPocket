<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessReceiptJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'photo' => ['required', 'image', 'max:8192'], // 8 Mo max
        ]);

        $traitementId = (string) Str::uuid();
        $chemin = $request->file('photo')->store('recus_temporaires', 'local');

        Cache::put("traitement_recu:{$traitementId}", ['statut' => 'en_cours'], now()->addMinutes(30));

        ProcessReceiptJob::dispatch($request->user(), $chemin, $traitementId);

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
        $resultat = Cache::get("traitement_recu:{$traitementId}");

        if (! $resultat) {
            return response()->json([
                'message' => 'Traitement introuvable ou expiré.',
            ], 404);
        }

        return response()->json($resultat);
    }
}
