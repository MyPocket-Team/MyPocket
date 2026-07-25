<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('titre')->after('user_id')->nullable();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('titre');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->enum('type', ['revenu', 'depense'])->after('nom')->nullable();
        });
    }
};
