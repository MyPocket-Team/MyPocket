<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categorie' => new CategorieResource($this->whenLoaded('categorie')),
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
            'titre' => $this->titre,
            'description' => $this->description,
            'montant_prevu' => $this->montant_prevu,
            'date_prevue' => $this->date_prevue,
            'statut' => $this->statut,
            'created_at' => $this->created_at,
        ];
    }
}
