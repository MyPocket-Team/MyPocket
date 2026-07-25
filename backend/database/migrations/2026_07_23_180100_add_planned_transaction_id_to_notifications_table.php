<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type')->change();
            $table->foreignId('planned_transaction_id')
                ->nullable()
                ->after('planning_id')
                ->constrained('planned_transactions')
                ->cascadeOnDelete();
            $table->integer('milestone')
                ->nullable()
                ->after('planned_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Revert back to enum if needed, but in rollback it's simpler to drop columns first.
            $table->dropForeign(['planned_transaction_id']);
            $table->dropColumn(['planned_transaction_id', 'milestone']);
            
            // To change it back to enum, we do:
            // $table->enum('type', ['rappel_planning', 'seuil_bas'])->change();
            // Note: enum change can sometimes fail if there are other types in the database, but let's make it work.
        });
        
        // Changing column back is safer outside dropping columns
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', ['rappel_planning', 'seuil_bas'])->change();
        });
    }
};
