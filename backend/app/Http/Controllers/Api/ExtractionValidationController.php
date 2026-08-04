<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateExtractionRequest;
use App\Http\Resources\PlannedTransactionResource;
use App\Http\Resources\PlanningResource;
use App\Http\Resources\TransactionResource;
use App\Models\Categorie;
use App\Models\Planning;
use App\Models\PlannedTransaction;
use App\Models\Transaction;

class ExtractionValidationController extends Controller
{
    /**
     * Valide une extraction IA (reçu, audio ou texte) après relecture par l'utilisateur.
     * Chaque entrée "realise" devient une Transaction réelle ; chaque entrée "en_attente"
     * devient une PlannedTransaction, regroupée sous un unique Planning créé pour ce lot.
     */
    public function valider(ValidateExtractionRequest $request, ?string $traitementId = null)
    {
        $data = $request->validated();
        $created = [
            'transactions' => [],
            'plannings' => [],
            'planned_transactions' => [],
        ];

        // Si un planning_id est fourni (saisie IA depuis la page Planning), toutes les
        // entrées sont rattachées à ce planning existant en tant que transactions
        // planifiées, quel que soit le statut envoyé.
        $planningExistant = null;
        if (! empty($data['planning_id'])) {
            $planningExistant = Planning::where('id', $data['planning_id'])
                ->where('user_id', $request->user()->id)
                ->first();
        }

        $planning = $planningExistant;

        foreach ($data['entries'] as $entry) {
            $categorieId = $entry['categorie_id'] ?? null;

            if (! empty($entry['nouvelle_categorie'])) {
                $categorie = Categorie::firstOrCreate([
                    'nom' => $entry['nouvelle_categorie'],
                    'user_id' => $request->user()->id,
                ]);
                $categorieId = $categorie->id;
            }

            $statut = $planningExistant ? 'en_attente' : $entry['statut'];

            if ($statut === 'realise') {
                $transaction = Transaction::create([
                    'user_id' => $request->user()->id,
                    'categorie_id' => $categorieId,
                    'montant' => $entry['montant'],
                    'description' => $entry['description'] ?? null,
                    'date_transaction' => $entry['date_transaction'],
                    'type' => $entry['type'],
                    'source' => $entry['source'] ?? 'ia_recu',
                ]);
                $created['transactions'][] = $transaction;
            } else {
                if (! $planning) {
                    $planning = Planning::create([
                        'user_id' => $request->user()->id,
                        'titre' => $this->genererTitrePlanning($entry['source'] ?? null),
                        'statut' => 'en_attente',
                    ]);
                    $created['plannings'][] = $planning;
                }

                $plannedTransaction = PlannedTransaction::create([
                    'user_id' => $request->user()->id,
                    'planning_id' => $planning->id,
                    'categorie_id' => $categorieId,
                    'montant' => $entry['montant'],
                    'description' => $entry['description'] ?? null,
                    'date_echeance' => $entry['date_transaction'],
                    'type' => $entry['type'],
                    'statut' => 'en_attente',
                ]);
                $created['planned_transactions'][] = $plannedTransaction;
            }
        }

        foreach ($created['transactions'] as $transaction) {
            $transaction->load('categorie');
        }
        foreach ($created['planned_transactions'] as $plannedTransaction) {
            $plannedTransaction->load('categorie');
        }

        return response()->json([
            'message' => 'Extraction validée et enregistrée.',
            'resultats' => [
                'transactions' => TransactionResource::collection($created['transactions']),
                'plannings' => PlanningResource::collection($created['plannings']),
                'planned_transactions' => PlannedTransactionResource::collection($created['planned_transactions']),
            ],
        ]);
    }

    private function genererTitrePlanning(?string $source): string
    {
        $labels = [
            'ia_recu' => 'Reçu scanné',
            'ia_audio' => 'Note vocale',
            'manuel' => 'Saisie texte',
        ];

        $label = $labels[$source] ?? 'Extraction IA';

        return "{$label} du ".now()->format('d/m/Y à H:i');
    }
}
