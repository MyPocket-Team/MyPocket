<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('planning_id')->constrained('plannings')->cascadeOnDelete();
            $table->foreignId('categorie_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('montant', 15, 2);
            $table->string('description')->nullable();
            $table->dateTime('date_echeance');
            $table->enum('type', ['depense', 'revenu']);
            $table->enum('statut', ['en_attente', 'realise', 'annule'])->default('en_attente');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_transactions');
    }
};
