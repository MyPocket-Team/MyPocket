<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * Liste des comptes utilisateurs (hors autres admins), avec recherche/filtrage.
     * ?recherche=nom_ou_email  &statut=actif|suspendu
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'user');

        if ($request->filled('recherche')) {
            $terme = $request->input('recherche');
            $query->where(function ($q) use ($terme) {
                $q->where('nom', 'like', "%{$terme}%")
                    ->orWhere('prenom', 'like', "%{$terme}%")
                    ->orWhere('email', 'like', "%{$terme}%");
            });
        }

        if ($request->filled('statut')) {
            $query->where('actif', $request->input('statut') === 'actif');
        }

        $users = $query
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return UserResource::collection($users);
    }

    public function show(User $user)
    {
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        return new UserResource($user);
    }

    /**
     * Suspend ou réactive un compte (droit d'action sur le compte uniquement,
     * aucune visibilité sur le détail des transactions de l'utilisateur).
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user)
    {
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $user->update(['actif' => $request->validated('actif')]);

        return response()->json([
            'message' => $user->actif
                ? 'Compte réactivé avec succès.'
                : 'Compte suspendu avec succès.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Suppression définitive du compte (droit à l'oubli) : cascade delete
     * de toutes les données liées (transactions, plannings, notifications,
     * catégories personnalisées, messages chatbot), assurée par les
     * contraintes cascadeOnDelete définies dans les migrations.
     */
    public function destroy(User $user)
    {
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $user->tokens()->delete(); // révoque tous les tokens Sanctum actifs
        $user->delete();

        return response()->json([
            'message' => 'Compte supprimé définitivement.',
        ]);
    }
}
