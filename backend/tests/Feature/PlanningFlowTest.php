<?php

use App\Models\Categorie;
use App\Models\Planning;
use App\Models\User;

function actingAsUser(): User
{
    $user = User::factory()->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}

test('un utilisateur peut créer un planning', function () {
    $user = actingAsUser();

    $response = $this->postJson('/api/plannings', [
        'titre' => 'Vacances',
        'description' => 'Objectif vacances de fin d\'année',
    ]);

    $response->assertCreated()
        ->assertJsonPath('planning.titre', 'Vacances')
        ->assertJsonPath('planning.statut', 'en_attente');

    expect(Planning::where('user_id', $user->id)->count())->toBe(1);
});

test('un utilisateur ne voit que ses propres plannings', function () {
    $user = actingAsUser();
    $autre = User::factory()->create();

    Planning::create(['user_id' => $user->id, 'titre' => 'À moi', 'statut' => 'en_attente']);
    Planning::create(['user_id' => $autre->id, 'titre' => 'Pas à moi', 'statut' => 'en_attente']);

    $response = $this->getJson('/api/plannings');

    $response->assertOk();
    $titres = collect($response->json('data'))->pluck('titre');
    expect($titres)->toContain('À moi')->not->toContain('Pas à moi');
});

test('un utilisateur peut modifier et annuler son planning', function () {
    $user = actingAsUser();
    $planning = Planning::create(['user_id' => $user->id, 'titre' => 'Original', 'statut' => 'en_attente']);

    $this->putJson("/api/plannings/{$planning->id}", ['titre' => 'Modifié'])
        ->assertOk()
        ->assertJsonPath('planning.titre', 'Modifié');

    $this->postJson("/api/plannings/{$planning->id}/annuler")
        ->assertOk();

    expect($planning->fresh()->statut)->toBe('annule');
});

test('un utilisateur peut supprimer son planning', function () {
    $user = actingAsUser();
    $planning = Planning::create(['user_id' => $user->id, 'titre' => 'À supprimer', 'statut' => 'en_attente']);

    $this->deleteJson("/api/plannings/{$planning->id}")->assertOk();

    expect(Planning::find($planning->id))->toBeNull();
});

test('un utilisateur ne peut pas modifier le planning d\'un autre', function () {
    actingAsUser();
    $autre = User::factory()->create();
    $planning = Planning::create(['user_id' => $autre->id, 'titre' => 'Pas à moi', 'statut' => 'en_attente']);

    $this->putJson("/api/plannings/{$planning->id}", ['titre' => 'Hack'])
        ->assertStatus(403);

    $this->deleteJson("/api/plannings/{$planning->id}")
        ->assertStatus(403);
});

test('un utilisateur peut planifier une transaction, la lister, la cocher et la supprimer', function () {
    $user = actingAsUser();
    $categorie = Categorie::create(['nom' => 'Alimentation', 'user_id' => $user->id]);
    $planning = Planning::create(['user_id' => $user->id, 'titre' => 'Budget mensuel', 'statut' => 'en_attente']);

    // Planifier
    $store = $this->postJson("/api/plannings/{$planning->id}/transactions-planifiees", [
        'categorie_id' => $categorie->id,
        'montant' => 15000,
        'description' => 'Courses du mois',
        'date_echeance' => now()->addDays(5)->toDateString(),
        'type' => 'depense',
    ]);
    $store->assertCreated();
    $plannedId = $store->json('planned_transaction.id');

    // Lister
    $this->getJson("/api/plannings/{$planning->id}/transactions-planifiees")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Cocher (confirmer)
    $confirm = $this->postJson("/api/transactions-planifiees/{$plannedId}/confirmer");
    $confirm->assertOk()
        ->assertJsonPath('planned_transaction.statut', 'realise');

    expect($user->fresh()->transactions()->count())->toBe(1);

    // Confirmer une deuxième fois doit échouer
    $this->postJson("/api/transactions-planifiees/{$plannedId}/confirmer")
        ->assertStatus(422);

    // Supprimer
    $store2 = $this->postJson("/api/plannings/{$planning->id}/transactions-planifiees", [
        'categorie_id' => $categorie->id,
        'montant' => 3000,
        'date_echeance' => now()->addDays(2)->toDateString(),
        'type' => 'depense',
    ]);
    $secondId = $store2->json('planned_transaction.id');

    $this->deleteJson("/api/transactions-planifiees/{$secondId}")->assertOk();

    $this->getJson("/api/plannings/{$planning->id}/transactions-planifiees")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
