<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('solde_initial_date')->nullable()->after('solde_initial');
        });

        // Pour les comptes existants, on prend la date de création du compte comme
        // date d'initialisation du solde (meilleure approximation disponible).
        DB::table('users')
            ->whereNull('solde_initial_date')
            ->update(['solde_initial_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('solde_initial_date');
        });
    }
};
