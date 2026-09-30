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
        Schema::create('token_purchases', function (Blueprint $table) {
            $table->id();
            // Convention du projet (cf. create_user_cards_table) : colonne + FK explicite
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 18, 2); // Montant total en SNT
            $table->integer('quantity')->default(1);
            $table->string('source', 32); // 'marketplace_purchase', 'card_purchase', ...
            $table->string('tx_hash')->nullable()->unique(); // Idempotence face aux retry
            $table->timestamps();

            $table->index('created_at');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_purchases');
    }
};