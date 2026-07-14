<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Liste les transactions de l'utilisateur connecté, avec filtres optionnels.
     * Filtres supportés : type, categorie_id, date_debut, date_fin.
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

        $transactions = $query
            ->orderByDesc('date_transaction')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 20));

        return TransactionResource::collection($transactions);
    }

    public function store(StoreTransactionRequest $request)
    {
        $transaction = Transaction::create([
            ...$request->validated(),
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
}
