<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox des effets de bord (humains).
 *
 * Le moteur de jeu calcule le résultat d'une session à la même vitesse pour les
 * bots et les humains. Les effets « lents » (payout on-chain, notifications,
 * signatures, syncLimits, cooldown, broadcasts) sont écrits dans cette table et
 * traités en ASYNCHRONE par `outbox:process` / le queue-worker.
 *
 * Résultat : la boucle de jeu n'attend jamais un paiement ou un broadcast.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('side_effect_outbox')) {
            return;
        }

        Schema::create('side_effect_outbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('type')->index(); // payout, session_finished, notification, sync_limits, cooldown
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])
                ->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('side_effect_outbox');
    }
};
