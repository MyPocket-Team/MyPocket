<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategorieRequest;
use App\Http\Resources\CategorieResource;
use App\Models\Categorie;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    /**
     * Liste les catégories visibles par l'utilisateur connecté :
     * catégories système + ses propres catégories personnalisées.
     */
    public function index(Request $request)
    {
        $categories = Categorie::visiblesPour($request->user()->id)
            ->orderBy('nom')
            ->get();

        return CategorieResource::collection($categories);
    }

    public function store(StoreCategorieRequest $request)
    {
        $categorie = Categorie::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Catégorie créée avec succès.',
            'categorie' => new CategorieResource($categorie),
        ], 201);
    }

    public function destroy(Request $request, Categorie $categorie)
    {
        // Une catégorie système (user_id = null) ne peut pas être supprimée.
        if (is_null($categorie->user_id)) {
            return response()->json([
                'message' => 'Impossible de supprimer une catégorie système.',
            ], 403);
        }

        // On ne peut supprimer que ses propres catégories.
        if ($categorie->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $categorie->delete();

        return response()->json([
            'message' => 'Catégorie supprimée avec succès.',
        ]);
    }
}
