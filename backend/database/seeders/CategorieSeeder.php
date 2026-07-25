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
            ['nom' => 'Alimentation', 'icone' => 'utensils'],
            ['nom' => 'Transport', 'icone' => 'car'],
            ['nom' => 'Logement', 'icone' => 'home'],
            ['nom' => 'Santé', 'icone' => 'heart-pulse'],
            ['nom' => 'Éducation', 'icone' => 'book'],
            ['nom' => 'Loisirs', 'icone' => 'gamepad'],
            ['nom' => 'Vêtements', 'icone' => 'shirt'],
            ['nom' => 'Factures & Abonnements', 'icone' => 'file-text'],
            ['nom' => 'Communication (crédit, internet)', 'icone' => 'phone'],
            ['nom' => 'Famille & Dons', 'icone' => 'users'],
            ['nom' => 'Autres dépenses', 'icone' => 'more-horizontal'],

            // Revenus
            ['nom' => 'Salaire', 'icone' => 'wallet'],
            ['nom' => 'Business / Commerce', 'icone' => 'store'],
            ['nom' => 'Freelance / Prestation', 'icone' => 'briefcase'],
            ['nom' => 'Transfert reçu', 'icone' => 'arrow-down-left'],
            ['nom' => 'Autres revenus', 'icone' => 'more-horizontal'],
        ];

        foreach ($categories as $categorie) {
            Categorie::firstOrCreate(
                ['nom' => $categorie['nom'], 'user_id' => null],
                $categorie
            );
        }
    }
}
