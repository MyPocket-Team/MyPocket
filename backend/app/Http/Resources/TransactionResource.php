<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categorie' => new CategorieResource($this->whenLoaded('categorie')),
            'montant' => $this->montant,
            'description' => $this->description,
            // Correction Problème 7 : Formatage propre "YYYY-MM-DD à HH:mm:ss"
            'date_transaction' => $this->date_transaction 
                ? Carbon::parse($this->date_transaction)->format('Y-m-d \à H:i:s') 
                : null,
            // Correction Problème 7 : Ajout de la date brute (YYYY-MM-DD) pour faciliter le regroupement côté Vue.js
            'date_brute' => $this->date_transaction 
                ? Carbon::parse($this->date_transaction)->format('Y-m-d') 
                : null,
            'solde_avant' => $this->solde_avant,
            'solde_apres' => $this->solde_apres,
            'type' => $this->type,
            'source' => $this->source,
            'created_at' => $this->created_at,
        ];
    }
}
