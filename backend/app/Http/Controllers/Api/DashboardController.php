<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Vue d'ensemble : solde actuel, total revenus/dépenses du mois en cours.
     */
    public function overview(Request $request)
    {
        $user = $request->user();
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

        return response()->json([
            'solde_initial' => $user->solde_initial,
            'solde_actuel' => $user->solde_actuel,
            'seuil_alerte' => $user->seuil_alerte,
            'seuil_bas_atteint' => $user->seuilBasAtteint(),
            'total_revenus_mois' => $totalRevenusMois,
            'total_depenses_mois' => $totalDepensesMois,
        ]);
    }

    /**
     * Évolution du solde dans le temps (pour le graphique de courbe).
     * Renvoie la liste des points (date, solde_apres) triés chronologiquement.
     */
    public function evolutionSolde(Request $request)
    {
        $user = $request->user();

        $dateDebut = $request->input('date_debut', now()->subMonths(1)->toDateString());
        $dateFin = $request->input('date_fin', now()->toDateString());

        $points = Transaction::where('user_id', $user->id)
            ->whereBetween('date_transaction', [$dateDebut, $dateFin])
            ->orderBy('date_transaction')
            ->orderBy('id')
            ->get(['date_transaction', 'solde_apres'])
            ->map(fn ($t) => [
                'date' => $t->date_transaction->toDateString(),
                'solde' => $t->solde_apres,
            ]);

        return response()->json(['evolution' => $points]);
    }

    /**
     * Répartition des dépenses par catégorie sur une période donnée (par défaut le mois en cours).
     * Pour un graphique en camembert/donut.
     */
    public function repartitionParCategorie(Request $request)
    {
        $user = $request->user();

        $dateDebut = $request->input('date_debut', now()->startOfMonth()->toDateString());
        $dateFin = $request->input('date_fin', now()->endOfMonth()->toDateString());
        $type = $request->input('type', 'depense'); // depense ou revenu

        $repartition = Transaction::where('transactions.user_id', $user->id)
            ->where('transactions.type', $type)
            ->whereBetween('transactions.date_transaction', [$dateDebut, $dateFin])
            ->join('categories', 'categories.id', '=', 'transactions.categorie_id')
            ->select('categories.id', 'categories.nom', 'categories.icone')
            ->selectRaw('SUM(transactions.montant) as total')
            ->groupBy('categories.id', 'categories.nom', 'categories.icone')
            ->orderByDesc('total')
            ->get();

        return response()->json(['repartition' => $repartition]);
    }

    /**
     * Comparaison mois par mois (revenus vs dépenses) sur les N derniers mois.
     */
    public function comparaisonMensuelle(Request $request)
    {
        $user = $request->user();
        $nombreMois = (int) $request->input('mois', 6);

        $dateDebut = now()->subMonths($nombreMois - 1)->startOfMonth();

        $donnees = Transaction::where('user_id', $user->id)
            ->where('date_transaction', '>=', $dateDebut)
            ->select(
                DB::raw("DATE_FORMAT(date_transaction, '%Y-%m') as mois"),
                'type'
            )
            ->selectRaw('SUM(montant) as total')
            ->groupBy('mois', 'type')
            ->orderBy('mois')
            ->get()
            ->groupBy('mois')
            ->map(function ($group, $mois) {
                return [
                    'mois' => $mois,
                    'revenus' => $group->firstWhere('type', 'revenu')->total ?? 0,
                    'depenses' => $group->firstWhere('type', 'depense')->total ?? 0,
                ];
            })
            ->values();

        return response()->json(['comparaison' => $donnees]);
    }
}
