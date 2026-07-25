<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AudioController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthGoogleController;
use App\Http\Controllers\Api\CategorieController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExtractionValidationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PlanningController;
use App\Http\Controllers\Api\PlannedTransactionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\TraitementController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']); // même endpoint pour user et super_admin
Route::post('/login/google', [AuthGoogleController::class, 'login']);

// Mot de passe oublié (throttle : max 5 requêtes/minute par IP, contre le spam d'emails et le brute-force du code)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'envoyerCode']);
    Route::post('/forgot-password/verify-code', [PasswordResetController::class, 'verifierCode']);
    Route::post('/reset-password', [PasswordResetController::class, 'reinitialiser']);
});

// Routes protégées (nécessitent un token Sanctum valide)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/transactions/parse-text', [TransactionController::class, 'parse']);
    Route::post('/transactions/valider-extraction', [ExtractionValidationController::class, 'valider']);
    Route::get('/profil', [ProfileController::class, 'show']);
    Route::post('/profil', [ProfileController::class, 'update']);

    Route::get('/categories', [CategorieController::class, 'index']);
    Route::post('/categories', [CategorieController::class, 'store']);
    Route::delete('/categories/{categorie}', [CategorieController::class, 'destroy']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

    Route::get('/plannings', [PlanningController::class, 'index']);
    Route::post('/plannings', [PlanningController::class, 'store']);
    Route::get('/plannings/{planning}', [PlanningController::class, 'show']);
    Route::post('/plannings/{planning}/lier', [PlanningController::class, 'link']);
    Route::post('/plannings/{planning}/annuler', [PlanningController::class, 'annuler']);
    Route::put('/plannings/{planning}', [PlanningController::class, 'update']);
    Route::delete('/plannings/{planning}', [PlanningController::class, 'destroy']);

    // Routes pour les transactions planifiées
    Route::get('/plannings/{planning}/transactions-planifiees', [PlannedTransactionController::class, 'index']);
    Route::post('/plannings/{planning}/transactions-planifiees', [PlannedTransactionController::class, 'store']);
    Route::delete('/transactions-planifiees/{plannedTransaction}', [PlannedTransactionController::class, 'destroy']);
    Route::post('/transactions-planifiees/{plannedTransaction}/confirmer', [PlannedTransactionController::class, 'confirmer']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/compteur', [NotificationController::class, 'compteurNonLues']);
    Route::post('/notifications/{notification}/lue', [NotificationController::class, 'marquerCommeLue']);
    Route::post('/notifications/tout-marquer-lu', [NotificationController::class, 'toutMarquerCommeLu']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/evolution-solde', [DashboardController::class, 'evolutionSolde']);
    Route::get('/dashboard/repartition-categories', [DashboardController::class, 'repartitionParCategorie']);
    Route::get('/dashboard/comparaison-mensuelle', [DashboardController::class, 'comparaisonMensuelle']);

    Route::post('/recus/upload', [ReceiptController::class, 'upload']);
    Route::get('/recus/{traitementId}/statut', [ReceiptController::class, 'statut']);
    Route::post('/recus/{traitementId}/valider', [ExtractionValidationController::class, 'valider']);
    Route::patch('/traitements/{traitementId}/finaliser', [TraitementController::class, 'finaliser']);

    Route::post('/audios/upload', [AudioController::class, 'upload']);
    Route::get('/audios/{traitementId}/statut', [AudioController::class, 'statut']);
    Route::post('/audios/{traitementId}/valider', [ExtractionValidationController::class, 'valider']);

    Route::get('/chatbot/conversations', [ChatbotController::class, 'conversations']);
    Route::post('/chatbot/conversations', [ChatbotController::class, 'storeConversation']);
    Route::delete('/chatbot/conversations/{conversation}', [ChatbotController::class, 'destroyConversation']);
    Route::get('/chatbot/conversations/{conversation}/messages', [ChatbotController::class, 'index']);
    Route::post('/chatbot/messages', [ChatbotController::class, 'store']);

    // --- Routes Super Admin ---
    Route::middleware('super_admin')->prefix('admin')->group(function () {
        Route::get('/dashboard/overview', [AdminDashboardController::class, 'overview']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/statut', [AdminUserController::class, 'updateStatus']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    });
});
