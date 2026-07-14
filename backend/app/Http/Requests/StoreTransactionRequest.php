<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categorie_id' => [
                'required',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)
                        ->orWhereNull('user_id');
                }),
            ],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date_transaction' => ['required', 'date'],
            'type' => ['required', 'in:revenu,depense'],
            'source' => ['nullable', 'in:manuel,ia_recu,ia_audio'],
        ];
    }

    public function messages(): array
    {
        return [
            'categorie_id.exists' => "Cette catégorie n'existe pas ou ne vous appartient pas.",
            'montant.min' => 'Le montant doit être supérieur à 0.',
        ];
    }
}
