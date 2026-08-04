<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Met à jour le profil de l'utilisateur connecté.
     * Marque profil_complete à true dès que les champs essentiels sont remplis.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $soldeInitialModifie = isset($data['solde_initial']) && $data['solde_initial'] != $user->solde_initial;

        if ($soldeInitialModifie) {
            // Mémorise la date à laquelle le solde de départ a été (re)défini, pour que
            // le graphique d'évolution puisse démarrer sur ce point précis.
            $data['solde_initial_date'] = now()->toDateString();
        }

        if ($request->hasFile('photo')) {
            // Toujours le disque "public" : contrairement à FILESYSTEM_DISK (utilisé pour
            // les fichiers privés temporaires des reçus/audios), les photos de profil
            // doivent toujours être servables publiquement via /storage/...
            $disk = 'public';

            // Supprime l'ancienne photo si elle existe
            if ($user->photo && Storage::disk($disk)->exists($user->photo)) {
                Storage::disk($disk)->delete($user->photo);
            }

            // Enregistre la nouvelle photo sur le stockage cloud
            $data['photo'] = $request->file('photo')->store('profils', $disk);
        }

        $user->update($data);

        if ($soldeInitialModifie) {
            $solde = (float) $data['solde_initial'];
            $transactions = $user->transactions()
                ->orderBy('date_transaction')
                ->orderBy('id')
                ->get();

            \App\Models\Transaction::withoutEvents(function () use ($transactions, &$solde) {
                foreach ($transactions as $transaction) {
                    $soldeAvant = $solde;
                    $soldeApres = $transaction->type === 'revenu'
                        ? $soldeAvant + $transaction->montant
                        : $soldeAvant - $transaction->montant;

                    $transaction->update([
                        'solde_avant' => $soldeAvant,
                        'solde_apres' => $soldeApres,
                    ]);

                    $solde = $soldeApres;
                }
            });

            $user->update([
                'solde_actuel' => $solde,
            ]);
        }

        // Recalcule dynamiquement le flag profil_complete
        $profilComplet = (bool) ($user->nom && $user->activite);
        if ($user->profil_complete !== $profilComplet) {
            $user->update(['profil_complete' => $profilComplet]);
        }

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function show(Request $request)
    {
        return new UserResource($request->user());
    }
}
