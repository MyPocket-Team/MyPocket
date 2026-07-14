<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAudioJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AudioController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg', 'max:10240'], // 10 Mo max
        ]);

        $traitementId = (string) Str::uuid();
        $chemin = $request->file('audio')->store('audios_temporaires', 'local');

        Cache::put("traitement_audio:{$traitementId}", ['statut' => 'en_cours'], now()->addMinutes(30));

        ProcessAudioJob::dispatch($request->user(), $chemin, $traitementId);

        return response()->json([
            'message' => 'Audio envoyé pour analyse.',
            'traitement_id' => $traitementId,
        ], 202);
    }

    public function statut(Request $request, string $traitementId)
    {
        $resultat = Cache::get("traitement_audio:{$traitementId}");

        if (! $resultat) {
            return response()->json([
                'message' => 'Traitement introuvable ou expiré.',
            ], 404);
        }

        return response()->json($resultat);
    }
}
