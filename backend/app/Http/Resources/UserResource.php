<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'role' => $this->role,
            'telephone' => $this->telephone,
            'activite' => $this->activite,
            'photo' => $this->photo,
            'seuil_alerte' => $this->seuil_alerte,
            'profil_complete' => $this->profil_complete,
            'actif' => $this->actif,
            'solde_initial' => $this->solde_initial,
            'solde_actuel' => $this->solde_actuel,
            'created_at' => $this->created_at,
        ];
    }
}
