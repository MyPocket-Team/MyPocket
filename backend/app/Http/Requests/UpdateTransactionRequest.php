<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categorie_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)
                        ->orWhereNull('user_id');
                }),
            ],
            'nouvelle_categorie' => ['nullable', 'string', 'max:255'],
            'montant' => ['nullable', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date_transaction' => ['nullable', 'date'],
            'type' => ['nullable', 'in:revenu,depense'],
            'source' => ['nullable', 'in:manuel,ia_recu,ia_audio,texte'],
        ];
    }
}
