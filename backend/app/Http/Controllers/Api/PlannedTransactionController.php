<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlannedTransactionResource;
use App\Models\Planning;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\Categorie;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlannedTransactionController extends Controller
{
    public function index(Request $request, Planning $planning)
    {
        if ($planning->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $plannedTransactions = $planning->plannedTransactions()
            ->with(['categorie'])
            ->orderBy('date_echeance', 'asc')
            ->get();

        return PlannedTransactionResource::collection($plannedTransactions);
    }

    public function store(Request $request, Planning $planning)
    {
        if ($planning->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $data = $request->validate([
            'categorie_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id)
                        ->orWhereNull('user_id');
                })
            ],
            'nouvelle_categorie' => ['nullable', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date_echeance' => ['required', 'date'],
            'type' => ['required', 'in:depense,revenu'],
        ]);

        if ($request->filled('nouvelle_categorie')) {
            $categorie = Categorie::create([
                'nom' => $request->input('nouvelle_categorie'),
                'user_id' => $request->user()->id,
            ]);
            $data['categorie_id'] = $categorie->id;
        }

        unset($data['nouvelle_categorie']);

        $plannedTransaction = PlannedTransaction::create([
            ...$data,
            'user_id' => $request->user()->id,
            'planning_id' => $planning->id,
            'statut' => 'en_attente',
        ]);

        $plannedTransaction->load(['categorie']);

        return response()->json([
            'message' => 'Transaction planifiée avec succès.',
            'planned_transaction' => new PlannedTransactionResource($plannedTransaction),
        ], 201);
    }

    public function destroy(Request $request, PlannedTransaction $plannedTransaction)
    {
        if ($plannedTransaction->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $planning = $plannedTransaction->planning;
        $plannedTransaction->delete();

        // Si la suppression fait qu'il ne reste plus que des transactions
        // réalisées (ou plus aucune), on peut faire passer le planning à "réalisé".
        if ($planning && $planning->statut === 'en_attente') {
            $toutesRealisees = $planning->plannedTransactions()->exists()
                && ! $planning->plannedTransactions()->where('statut', '!=', 'realise')->exists();

            if ($toutesRealisees) {
                $planning->update(['statut' => 'realise']);
            }
        }

        return response()->json([
            'message' => 'Transaction planifiée supprimée avec succès.',
        ]);
    }

    public function confirmer(Request $request, PlannedTransaction $plannedTransaction)
    {
        if ($plannedTransaction->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        if ($plannedTransaction->statut === 'realise') {
            return response()->json([
                'message' => 'Cette transaction planifiée a déjà été réalisée.',
            ], 422);
        }

        // Crée une vraie transaction
        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'categorie_id' => $plannedTransaction->categorie_id,
            'montant' => $plannedTransaction->montant,
            'description' => $plannedTransaction->description ?: "Transaction planifiée réalisee",
            'date_transaction' => now(),
            'type' => $plannedTransaction->type,
            'source' => 'manuel',
        ]);

        // Met à jour la transaction planifiée
        $plannedTransaction->update([
            'statut' => 'realise',
            'transaction_id' => $transaction->id,
        ]);

        // Si toutes les transactions planifiées de ce planning sont désormais
        // réalisées, on fait passer le planning parent lui-même à "réalisé".
        $planning = $plannedTransaction->planning;
        if ($planning && $planning->statut === 'en_attente') {
            $toutesRealisees = $planning->plannedTransactions()->exists()
                && ! $planning->plannedTransactions()->where('statut', '!=', 'realise')->exists();

            if ($toutesRealisees) {
                $planning->update(['statut' => 'realise']);
            }
        }

        $plannedTransaction->load(['categorie']);

        return response()->json([
            'message' => 'Transaction planifiée confirmée avec succès.',
            'planned_transaction' => new PlannedTransactionResource($plannedTransaction),
            'solde_actuel' => $request->user()->fresh()->solde_actuel,
        ]);
    }
}
