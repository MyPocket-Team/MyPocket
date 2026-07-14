<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->enum('role', ['user', 'super_admin'])->default('user');
            $table->string('telephone')->nullable();
            $table->string('activite')->nullable();
            $table->string('photo')->nullable();
            $table->float('seuil_alerte')->default(90);
            $table->boolean('profil_complete')->default(false);
            $table->boolean('actif')->default(true);
            $table->decimal('solde_initial', 15, 2)->default(0);
            $table->decimal('solde_actuel', 15, 2)->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
