<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.statut' => ['required', 'in:realise,en_attente'],
            'entries.*.montant' => ['required', 'numeric', 'min:0.01'],
            'entries.*.type' => ['required', 'in:revenu,depense'],
            'entries.*.description' => ['nullable', 'string', 'max:255'],
            // Sert de date_transaction si "realise", de date_echeance si "en_attente" : requise dans les deux cas.
            'entries.*.date_transaction' => ['required', 'date'],
            'entries.*.categorie_id' => [
                'nullable',
                'required_without:entries.*.nouvelle_categorie',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)
                        ->orWhereNull('user_id');
                }),
            ],
            'entries.*.nouvelle_categorie' => ['nullable', 'string', 'max:255'],
            'entries.*.source' => ['nullable', 'in:manuel,ia_recu,ia_audio'],
        ];
    }
}
