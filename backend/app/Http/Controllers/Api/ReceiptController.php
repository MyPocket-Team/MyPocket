<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessReceiptJob;
use App\Models\TraitementTranscription;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        TraitementTranscription::create([
            'uuid' => $traitementId,
            'user_id' => $request->user()->id,
            'type' => 'recu',
            'status' => 'en_cours',
            'file_path' => $chemin,
        ]);

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
