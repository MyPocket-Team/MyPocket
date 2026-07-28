<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Génère l'URL complète vers ton stockage cloud ou local
        $photoUrl = null;
        if ($this->photo) {
            $photoUrl = str_starts_with($this->photo, 'http://') || str_starts_with($this->photo, 'https://')
                ? $this->photo
                : Storage::disk(config('filesystems.default', 'public'))->url($this->photo);
        }

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'role' => $this->role,
            'telephone' => $this->telephone,
            'activite' => $this->activite,
            'photo' => $photoUrl,
            'seuil_alerte' => $this->seuil_alerte,
            'profil_complete' => $this->profil_complete,
            'actif' => $this->actif,
            'solde_initial' => $this->solde_initial,
            'solde_actuel' => $this->solde_actuel,
            'created_at' => $this->created_at,
        ];
    }
}
