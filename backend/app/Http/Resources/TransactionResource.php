<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categorie' => new CategorieResource($this->whenLoaded('categorie')),
            'montant' => $this->montant,
            'description' => $this->description,
            'date_transaction' => $this->date_transaction,
            'solde_avant' => $this->solde_avant,
            'solde_apres' => $this->solde_apres,
            'type' => $this->type,
            'source' => $this->source,
            'created_at' => $this->created_at,
        ];
    }
}
