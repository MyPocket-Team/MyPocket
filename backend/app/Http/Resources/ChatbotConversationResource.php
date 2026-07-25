<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatbotConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dernierMessage = $this->relationLoaded('dernierMessage')
            ? $this->dernierMessage
            : $this->messages->last();

        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'dernier_message' => $dernierMessage?->contenu,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}