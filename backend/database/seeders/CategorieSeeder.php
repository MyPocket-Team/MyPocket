<?php

namespace Database\Seeders;

use App\Models\Categorie;
use Illuminate\Database\Seeder;

class CategorieSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Dépenses
            ['nom' => 'Alimentation', 'type' => 'depense', 'icone' => 'utensils'],
            ['nom' => 'Transport', 'type' => 'depense', 'icone' => 'car'],
            ['nom' => 'Logement', 'type' => 'depense', 'icone' => 'home'],
            ['nom' => 'Santé', 'type' => 'depense', 'icone' => 'heart-pulse'],
            ['nom' => 'Éducation', 'type' => 'depense', 'icone' => 'book'],
            ['nom' => 'Loisirs', 'type' => 'depense', 'icone' => 'gamepad'],
            ['nom' => 'Vêtements', 'type' => 'depense', 'icone' => 'shirt'],
            ['nom' => 'Factures & Abonnements', 'type' => 'depense', 'icone' => 'file-text'],
            ['nom' => 'Communication (crédit, internet)', 'type' => 'depense', 'icone' => 'phone'],
            ['nom' => 'Famille & Dons', 'type' => 'depense', 'icone' => 'users'],
            ['nom' => 'Autres dépenses', 'type' => 'depense', 'icone' => 'more-horizontal'],

            // Revenus
            ['nom' => 'Salaire', 'type' => 'revenu', 'icone' => 'wallet'],
            ['nom' => 'Business / Commerce', 'type' => 'revenu', 'icone' => 'store'],
            ['nom' => 'Freelance / Prestation', 'type' => 'revenu', 'icone' => 'briefcase'],
            ['nom' => 'Transfert reçu', 'type' => 'revenu', 'icone' => 'arrow-down-left'],
            ['nom' => 'Autres revenus', 'type' => 'revenu', 'icone' => 'more-horizontal'],
        ];

        foreach ($categories as $categorie) {
            Categorie::firstOrCreate(
                ['nom' => $categorie['nom'], 'user_id' => null],
                $categorie
            );
        }
    }
}
