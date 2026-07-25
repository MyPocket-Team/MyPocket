<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotMessage;
use App\Models\Transaction;
use App\Models\TraitementTranscription;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Vue d'ensemble : nombre d'utilisateurs, volume de transactions,
     * compteur de requêtes Gemini (reçus + audio + chatbot) et quota mensuel.
     */
    public function overview(Request $request)
    {
        $nombreUtilisateurs = User::where('role', 'user')->count();
        $nombreUtilisateursActifs = User::where('role', 'user')->where('actif', true)->count();

        $volumeTransactions = Transaction::count();
        $volumeTransactionsMois = Transaction::whereMonth('date_transaction', now()->month)
            ->whereYear('date_transaction', now()->year)
            ->count();

        $debutMois = now()->startOfMonth();
        $finMois = now()->endOfMonth();

        // 1 appel Gemini par traitement reçu/audio (indépendamment du nombre de
        // transactions extraites), et 1 appel Gemini par réponse assistant du chatbot
        // (un échange = 1 message "user" + 1 message "assistant" pour 1 seul appel).
        $appelsGeminiRecusAudio = TraitementTranscription::count();
        $appelsGeminiRecusAudioMois = TraitementTranscription::whereBetween('created_at', [$debutMois, $finMois])->count();

        $appelsGeminiChatbot = ChatbotMessage::where('role', 'assistant')->count();
        $appelsGeminiChatbotMois = ChatbotMessage::where('role', 'assistant')
            ->whereBetween('created_at', [$debutMois, $finMois])
            ->count();

        $totalAppelsGemini = $appelsGeminiRecusAudio + $appelsGeminiChatbot;
        $totalAppelsGeminiMois = $appelsGeminiRecusAudioMois + $appelsGeminiChatbotMois;

        $quotaMensuel = (int) config('services.gemini.quota_mensuel');
        $pourcentageQuotaUtilise = $quotaMensuel > 0
            ? round(($totalAppelsGeminiMois / $quotaMensuel) * 100, 1)
            : null;

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
                'ce_mois' => [
                    'appels_recus_audio' => $appelsGeminiRecusAudioMois,
                    'appels_chatbot' => $appelsGeminiChatbotMois,
                    'total' => $totalAppelsGeminiMois,
                    'quota' => $quotaMensuel,
                    'pourcentage_utilise' => $pourcentageQuotaUtilise,
                ],
            ],
        ]);
    }
}
