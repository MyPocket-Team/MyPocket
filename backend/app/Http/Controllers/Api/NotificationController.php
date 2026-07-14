<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Liste les notifications de l'utilisateur connecté.
     * Filtre optionnel : ?non_lues=1
     */
    public function index(Request $request)
    {
        $query = Notification::with('planning')
            ->where('user_id', $request->user()->id);

        if ($request->boolean('non_lues')) {
            $query->where('lue', false);
        }

        $notifications = $query
            ->orderByDesc('date_envoi')
            ->paginate($request->input('per_page', 20));

        return NotificationResource::collection($notifications);
    }

    /**
     * Retourne juste le compteur de notifications non lues.
     * Pratique pour un badge dans l'UI d'Astride, sans devoir tout charger.
     */
    public function compteurNonLues(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('lue', false)
            ->count();

        return response()->json(['non_lues' => $count]);
    }

    public function marquerCommeLue(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $notification->marquerCommeLue();

        return response()->json([
            'message' => 'Notification marquée comme lue.',
            'notification' => new NotificationResource($notification),
        ]);
    }

    /**
     * Marque toutes les notifications de l'utilisateur comme lues.
     */
    public function toutMarquerCommeLu(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('lue', false)
            ->update(['lue' => true]);

        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues.',
        ]);
    }

    public function destroy(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification supprimée avec succès.',
        ]);
    }
}
