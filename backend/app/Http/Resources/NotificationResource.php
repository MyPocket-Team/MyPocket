<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'planning' => new PlanningResource($this->whenLoaded('planning')),
            'type' => $this->type,
            'motif' => $this->motif,
            'lue' => $this->lue,
            'date_envoi' => $this->date_envoi,
            'created_at' => $this->created_at,
        ];
    }
}
