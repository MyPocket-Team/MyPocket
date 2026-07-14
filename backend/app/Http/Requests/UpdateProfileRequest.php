<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'activite' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seuil_alerte' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'photo' => ['sometimes', 'nullable', 'image', 'max:4096'], // 4 Mo max
        ];
    }
}
