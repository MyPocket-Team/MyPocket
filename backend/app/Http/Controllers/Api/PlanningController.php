<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkPlanningRequest;
use App\Http\Requests\StorePlanningRequest;
use App\Http\Resources\PlanningResource;
use App\Models\Planning;
use App\Models\Transaction;
use App\Models\Categorie;
use Illuminate\Http\Request;

class PlanningController extends Controller
{
    public function index(Request $request)
    {
        $query = Planning::with(['transaction'])
            ->where('user_id', $request->user()->id);

        if ($request->filled('statut')) {
            $query->where('statut', $request->input('statut'));
        }

        $plannings = $query
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return PlanningResource::collection($plannings);
    }

    public function store(StorePlanningRequest $request)
    {
        $data = $request->validated();

        if ($request->filled('nouvelle_categorie')) {
            $categorie = Categorie::create([
                'nom' => $request->input('nouvelle_categorie'),
                'user_id' => $request->user()->id,
            ]);
            $data['categorie_id'] = $categorie->id;
        }

        $planning = Planning::create([
            ...$data,
            'user_id' => $request->user()->id,
            'statut' => 'en_attente',
        ]);

        $planning->load('transaction');

        return response()->json([
            'message' => 'Planning créé avec succès.',
            'planning' => new PlanningResource($planning),
        ], 201);
    }

    public function show(Request $request, Planning $planning)
    {
        if ($planning->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $planning->load(['transaction']);

        return new PlanningResource($planning);
    }

    /**
     * Lie manuellement un planning à une transaction déjà créée.
     * Passe le statut du planning à "realise".
     */
    public function link(LinkPlanningRequest $request, Planning $planning)
    {
        if ($planning->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        if ($planning->statut === 'realise') {
            return response()->json([
                'message' => 'Ce planning est déjà lié à une transaction.',
            ], 422);
        }

        $transaction = Transaction::findOrFail($request->validated('transaction_id'));

        $planning->lierATransaction($transaction);
        $planning->load(['transaction']);

        return response()->json([
            'message' => 'Planning lié à la transaction avec succès.',
            'planning' => new PlanningResource($planning),
        ]);
    }

    /**
     * Annule un planning (ex: la transaction prévue n'aura finalement pas lieu).
     */
    public function annuler(Request $request, Planning $planning)
    {
        if ($planning->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $planning->update(['statut' => 'annule']);

        return response()->json([
            'message' => 'Planning annulé avec succès.',
        ]);
    }

    public function update(StorePlanningRequest $request, Planning $planning)
    {
        if ($planning->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $data = $request->validated();

        if ($request->filled('nouvelle_categorie')) {
            $categorie = Categorie::create([
                'nom' => $request->input('nouvelle_categorie'),
                'user_id' => $request->user()->id,
            ]);
            $data['categorie_id'] = $categorie->id;
        }

        $planning->update($data);
        $planning->load('transaction');

        return response()->json([
            'message' => 'Planning mis à jour avec succès.',
            'planning' => new PlanningResource($planning),
        ]);
    }

    public function destroy(Request $request, Planning $planning)
    {
        if ($planning->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $planning->delete();

        return response()->json([
            'message' => 'Planning supprimé avec succès.',
        ]);
    }
}
