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

        if ($request->hasFile('photo')) {
            // Supprime l'ancienne photo si elle existe
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }

            $data['photo'] = $request->file('photo')->store('profils', 'public');
        }

        $user->update($data);

        // Considère le profil comme complet si les infos essentielles sont là
        if (! $user->profil_complete && $user->nom && $user->activite) {
            $user->update(['profil_complete' => true]);
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
