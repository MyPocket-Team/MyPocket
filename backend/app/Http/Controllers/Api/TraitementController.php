<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TraitementTranscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TraitementController extends Controller
{
    public function finaliser(Request $request, string $traitementId)
    {
        $traitement = TraitementTranscription::where('uuid', $traitementId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $traitement) {
            return response()->json(['message' => 'Traitement introuvable.'], 404);
        }

        if ($traitement->status !== 'termine') {
            return response()->json([
                'message' => 'Le traitement doit être terminé avant de pouvoir être finalisé.',
            ], 422);
        }

        if ($traitement->file_path && Storage::disk('local')->exists($traitement->file_path)) {
            Storage::disk('local')->delete($traitement->file_path);
        }

        $traitement->delete();

        return response()->json([
            'message' => 'Traitement finalisé et supprimé avec succès.',
        ]);
    }
}
