<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotMessage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Vue d'ensemble : nombre d'utilisateurs, volume de transactions,
     * compteur de requêtes Gemini (approximé via nombre de transactions IA + messages chatbot).
     */
    public function overview(Request $request)
    {
        $nombreUtilisateurs = User::where('role', 'user')->count();
        $nombreUtilisateursActifs = User::where('role', 'user')->where('actif', true)->count();

        $volumeTransactions = Transaction::count();
        $volumeTransactionsMois = Transaction::whereMonth('date_transaction', now()->month)
            ->whereYear('date_transaction', now()->year)
            ->count();

        // Approximation du quota Gemini : chaque transaction source IA = 1 appel,
        // chaque message chatbot (user + assistant) = 1 appel.
        $appelsGeminiRecusAudio = Transaction::whereIn('source', ['ia_recu', 'ia_audio'])->count();
        $appelsGeminiChatbot = ChatbotMessage::count();
        $totalAppelsGemini = $appelsGeminiRecusAudio + $appelsGeminiChatbot;

        return response()->json([
            'utilisateurs' => [
                'total' => $nombreUtilisateurs,
                'actifs' => $nombreUtilisateursActifs,
                'suspendus' => $nombreUtilisateurs - $nombreUtilisateursActifs,
            ],
            'transactions' => [
                'total' => $volumeTransactions,
                'ce_mois' => $volumeTransactionsMois,
            ],
            'gemini' => [
                'appels_recus_audio' => $appelsGeminiRecusAudio,
                'appels_chatbot' => $appelsGeminiChatbot,
                'total_estime' => $totalAppelsGemini,
            ],
        ]);
    }
}
