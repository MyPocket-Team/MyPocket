<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlannedTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'planning_id' => $this->planning_id,
            'categorie' => new CategorieResource($this->whenLoaded('categorie')) ?: [
                'id' => $this->categorie_id,
                'nom' => $this->categorie?->nom,
            ],
            'montant' => $this->montant,
            'description' => $this->description,
            'date_echeance' => $this->date_echeance ? $this->date_echeance->toIso8601String() : null,
            'type' => $this->type,
            'statut' => $this->statut,
            'transaction_id' => $this->transaction_id,
            'created_at' => $this->created_at,
        ];
    }
}
