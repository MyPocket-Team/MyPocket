<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatbotMessageRequest;
use App\Http\Resources\ChatbotMessageResource;
use App\Models\ChatbotMessage;
use App\Models\Transaction;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function index(Request $request)
    {
        $messages = ChatbotMessage::where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get();

        return ChatbotMessageResource::collection($messages);
    }

    public function store(StoreChatbotMessageRequest $request, GeminiService $gemini)
    {
        $user = $request->user();

        // Sauvegarde le message de l'utilisateur
        $messageUser = ChatbotMessage::create([
            'user_id' => $user->id,
            'role' => 'user',
            'contenu' => $request->validated('message'),
        ]);

        // Construit l'historique (limité aux 20 derniers messages pour rester léger)
        $historique = ChatbotMessage::where('user_id', $user->id)
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($m) => [
                'role' => $m->role === 'assistant' ? 'model' : 'user',
                'text' => $m->contenu,
            ])
            ->toArray();

        $contexteFinancier = $this->construireContexteAgrege($user);

        $reponseTexte = $gemini->reponseChatbot($historique, $contexteFinancier);

        $messageAssistant = ChatbotMessage::create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'contenu' => $reponseTexte,
        ]);

        return response()->json([
            'message_utilisateur' => new ChatbotMessageResource($messageUser),
            'reponse' => new ChatbotMessageResource($messageAssistant),
        ], 201);
    }

    /**
     * Construit un résumé agrégé (jamais le détail transaction par transaction)
     * conformément à la politique de confidentialité : seules des données agrégées
     * sont envoyées à Gemini.
     */
    protected function construireContexteAgrege($user): array
    {
        $debutMois = now()->startOfMonth();
        $finMois = now()->endOfMonth();

        $totalRevenusMois = Transaction::where('user_id', $user->id)
            ->where('type', 'revenu')
            ->whereBetween('date_transaction', [$debutMois, $finMois])
            ->sum('montant');

        $totalDepensesMois = Transaction::where('user_id', $user->id)
            ->where('type', 'depense')
            ->whereBetween('date_transaction', [$debutMois, $finMois])
            ->sum('montant');

        $topCategoriesDepenses = Transaction::where('transactions.user_id', $user->id)
            ->where('transactions.type', 'depense')
            ->whereBetween('transactions.date_transaction', [$debutMois, $finMois])
            ->join('categories', 'categories.id', '=', 'transactions.categorie_id')
            ->selectRaw('categories.nom, SUM(transactions.montant) as total')
            ->groupBy('categories.nom')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'nom')
            ->toArray();

        return [
            'solde_actuel' => $user->solde_actuel,
            'solde_initial' => $user->solde_initial,
            'total_revenus_ce_mois' => $totalRevenusMois,
            'total_depenses_ce_mois' => $totalDepensesMois,
            'top_categories_depenses' => json_encode($topCategoriesDepenses, JSON_UNESCAPED_UNICODE),
            'seuil_alerte_pourcentage' => $user->seuil_alerte,
        ];
    }
}
