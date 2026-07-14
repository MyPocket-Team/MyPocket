<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AuthGoogleController extends Controller
{
    /**
     * Reçoit le id_token généré côté client par le SDK Google Identity Services
     * (Astride affiche le bouton Google, récupère le id_token, l'envoie ici).
     * Le backend vérifie ce token directement auprès de Google avant de créer la session.
     */
    public function login(GoogleLoginRequest $request)
    {
        $idToken = $request->validated('id_token');

        // Vérifie le token auprès de Google (endpoint officiel tokeninfo)
        $verification = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if ($verification->failed()) {
            return response()->json([
                'message' => 'Token Google invalide ou expiré.',
            ], 422);
        }

        $payload = $verification->json();

        // Vérifie que le token a bien été émis pour NOTRE application (anti-usurpation)
        $clientIdAttendu = config('services.google.client_id');
        if (($payload['aud'] ?? null) !== $clientIdAttendu) {
            return response()->json([
                'message' => 'Token Google non reconnu pour cette application.',
            ], 422);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'];
        $nom = $payload['name'] ?? $email;
        $photo = $payload['picture'] ?? null;

        $user = User::where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $googleId]);
            }

            if (! $user->actif) {
                return response()->json([
                    'message' => 'Ce compte a été suspendu.',
                ], 403);
            }
        } else {
            $user = User::create([
                'nom' => $nom,
                'email' => $email,
                'google_id' => $googleId,
                'photo' => $photo,
                'password' => null,
                'profil_complete' => false,
            ]);
        }

        $token = $user->createToken('mypocket_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion Google réussie.',
            'user' => new UserResource($user),
            'token' => $token,
            'nouveau_compte' => $user->wasRecentlyCreated,
        ]);
    }
}
