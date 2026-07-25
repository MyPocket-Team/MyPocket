<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')
                ->nullable()
                ->after('user_id')
                ->constrained('chatbot_conversations')
                ->cascadeOnDelete();
        });

        // Regroupe les anciens messages (créés avant cette migration, donc sans
        // conversation_id) dans une conversation "Ancienne discussion" par
        // utilisateur, pour ne perdre aucun historique existant.
        $userIds = DB::table('chatbot_messages')
            ->whereNull('conversation_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $conversationId = DB::table('chatbot_conversations')->insertGetId([
                'user_id' => $userId,
                'titre' => 'Ancienne discussion',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('chatbot_messages')
                ->where('user_id', $userId)
                ->whereNull('conversation_id')
                ->update(['conversation_id' => $conversationId]);
        }
    }

    public function down(): void
    {
        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });
    }
};