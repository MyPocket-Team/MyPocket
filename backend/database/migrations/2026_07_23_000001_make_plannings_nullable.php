<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            $table->decimal('montant_prevu', 15, 2)->nullable()->change();
            $table->dateTime('date_prevue')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plannings', function (Blueprint $table) {
            $table->decimal('montant_prevu', 15, 2)->nullable(false)->change();
            $table->dateTime('date_prevue')->nullable(false)->change();
        });
    }
};
