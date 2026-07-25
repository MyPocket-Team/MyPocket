<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Categorie;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Liste les transactions de l'utilisateur connecté, avec filtres optionnels.
     * Filtres supportés : type, categorie_id, date_debut, date_fin, recherche.
     */
    public function index(Request $request)
    {
        $query = Transaction::with('categorie')
            ->where('user_id', $request->user()->id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->input('categorie_id'));
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date_transaction', '>=', $request->input('date_debut'));
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date_transaction', '<=', $request->input('date_fin'));
        }

        if ($request->filled('recherche')) {
            $terme = $request->input('recherche');
            $query->where(function ($q) use ($terme) {
                $q->where('description', 'like', "%{$terme}%")
                    ->orWhereHas('categorie', fn ($sousQuery) => $sousQuery->where('nom', 'like', "%{$terme}%"));
            });
        }

        // Sorting
        $order = strtolower($request->input('ordre', 'desc')) === 'asc' ? 'asc' : 'desc';
        $tri = $request->input('tri', 'date_transaction');

        if ($tri === 'montant') {
            $query->orderBy('montant', $order);
        } elseif ($tri === 'categorie') {
            $query->join('categories', 'transactions.categorie_id', '=', 'categories.id')
                  ->orderBy('categories.nom', $order)
                  ->select('transactions.*');
        } else {
            $query->orderBy('date_transaction', $order)->orderBy('id', $order === 'asc' ? 'asc' : 'desc');
        }

        $transactions = $query->paginate($request->input('per_page', 20));

        return TransactionResource::collection($transactions);
    }

    public function store(StoreTransactionRequest $request)
    {
        $data = $request->validated();

        // Création de la catégorie à la volée si demandé
        if ($request->filled('nouvelle_categorie')) {
            $categorie = Categorie::create([
                'nom' => $request->input('nouvelle_categorie'),
                'user_id' => $request->user()->id,
            ]);
            $data['categorie_id'] = $categorie->id;
        }

        $transaction = Transaction::create([
            ...$data,
            'user_id' => $request->user()->id,
            'source' => $request->input('source', 'manuel'),
        ]);

        $transaction->load('categorie');

        return response()->json([
            'message' => 'Transaction enregistrée avec succès.',
            'transaction' => new TransactionResource($transaction),
            'solde_actuel' => $request->user()->fresh()->solde_actuel,
        ], 201);
    }

    public function show(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $transaction->load('categorie');

        return new TransactionResource($transaction);
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $transaction->delete();

        return response()->json([
            'message' => 'Transaction supprimée avec succès.',
            'solde_actuel' => $request->user()->fresh()->solde_actuel,
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $data = $request->validated();

        if ($request->filled('nouvelle_categorie')) {
            $categorie = Categorie::create([
                'nom' => $request->input('nouvelle_categorie'),
                'user_id' => $request->user()->id,
            ]);
            $data['categorie_id'] = $categorie->id;
        }

        $transaction->update($data);

        $transaction->load('categorie');

        return response()->json([
            'message' => 'Transaction mise à jour avec succès.',
            'transaction' => new TransactionResource($transaction),
            'solde_actuel' => $request->user()->fresh()->solde_actuel,
        ]);
    }

    public function parse(Request $request, GeminiService $geminiService)
    {
        $request->validate([
            'texte' => 'required|string|max:500',
        ]);

        $donnees = $geminiService->extraireDonnees($request->texte);

        $transactions = is_array($donnees['transactions'] ?? null) ? $donnees['transactions'] : [];
        $multiplesTransactions = $donnees['multiples_transactions'] ?? (count($transactions) > 1);

        return response()->json([
            'multiples_transactions' => $multiplesTransactions,
            'transactions' => array_map(
                fn (array $transaction) => [
                    ...$transaction,
                    'champs_manquants' => $this->detecterChampsManquants($transaction),
                ],
                $transactions
            ),
        ]);
    }

    private function detecterChampsManquants(array $donnees): array
    {
        $manquants = [];
        foreach (['montant', 'type', 'categorie'] as $champ) {
            if (empty($donnees[$champ])) {
                $manquants[] = $champ;
            }
        }
        return $manquants;
    }    
}