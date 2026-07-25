<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categorie_id' => ['nullable', Rule::exists('categories', 'id')->where(function ($query) {
                $query->where('user_id', $this->user()->id)
                    ->orWhereNull('user_id');
            })],
            'nouvelle_categorie' => ['nullable', 'string', 'max:255'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'date_prevue' => ['nullable', 'date'],
        ];
    }
}
