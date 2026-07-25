<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyResetCodeRequest;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    protected const DUREE_VALIDITE_MINUTES = 10;

    protected const TENTATIVES_MAX = 5;

    /**
     * Étape 1 : génère un code à 6 chiffres et l'envoie par email.
     * Réponse volontairement identique que le compte existe ou non, pour ne pas
     * révéler quels emails sont enregistrés (protection contre l'énumération de comptes).
     */
    public function envoyerCode(ForgotPasswordRequest $request)
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordResetCode::updateOrCreate(
                ['email' => $email],
                [
                    'code' => Hash::make($code),
                    'tentatives' => 0,
                    'expire_a' => now()->addMinutes(self::DUREE_VALIDITE_MINUTES),
                ]
            );

            Mail::raw(
                "Votre code de réinitialisation MyPocket est : {$code}\n\n".
                'Ce code expire dans '.self::DUREE_VALIDITE_MINUTES." minutes.\n".
                "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.",
                function ($message) use ($email) {
                    $message->to($email)->subject('Réinitialisation de votre mot de passe MyPocket');
                }
            );
        }

        return response()->json([
            'message' => 'Si un compte existe avec cet email, un code de réinitialisation vient d\'être envoyé.',
        ]);
    }

    /**
     * Étape 2 (optionnelle côté UX) : permet au frontend de vérifier le code
     * avant d'afficher l'écran de saisie du nouveau mot de passe.
     */
    public function verifierCode(VerifyResetCodeRequest $request)
    {
        $this->trouverCodeValide($request->validated('email'), $request->validated('code'));

        return response()->json([
            'message' => 'Code valide.',
        ]);
    }

    /**
     * Étape 3 : vérifie à nouveau le code puis remplace le mot de passe.
     * Toutes les sessions actives de l'utilisateur sont révoquées par sécurité.
     */
    public function reinitialiser(ResetPasswordRequest $request)
    {
        $email = $request->validated('email');
        $enregistrement = $this->trouverCodeValide($email, $request->validated('code'));

        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Aucun compte associé à cet email.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->validated('password'))]);
        $user->tokens()->delete();

        $enregistrement->delete();

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
        ]);
    }

    /**
     * @throws ValidationException si le code est absent, expiré, incorrect ou épuisé.
     */
    protected function trouverCodeValide(string $email, string $code): PasswordResetCode
    {
        $enregistrement = PasswordResetCode::where('email', $email)->first();

        if (! $enregistrement || $enregistrement->estExpire()) {
            throw ValidationException::withMessages([
                'code' => ['Le code est invalide ou a expiré. Veuillez en redemander un.'],
            ]);
        }

        if ($enregistrement->tentatives >= self::TENTATIVES_MAX) {
            $enregistrement->delete();

            throw ValidationException::withMessages([
                'code' => ['Trop de tentatives incorrectes. Veuillez redemander un code.'],
            ]);
        }

        if (! Hash::check($code, $enregistrement->code)) {
            $enregistrement->increment('tentatives');

            throw ValidationException::withMessages([
                'code' => ['Le code saisi est incorrect.'],
            ]);
        }

        return $enregistrement;
    }
}
