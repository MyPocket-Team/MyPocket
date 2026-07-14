<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategorieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'type' => $this->type,
            'icone' => $this->icone,
            'est_personnalisee' => ! is_null($this->user_id),
            'created_at' => $this->created_at,
        ];
    }
}
