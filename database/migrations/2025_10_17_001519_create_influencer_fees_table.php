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
        Schema::create('influencer_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); // L'utilisateur qui a généré les frais
            $table->string('language_code'); // La langue de l'utilisateur (fr, en...)
            $table->decimal('fee_amount_avax', 18, 8); // Le montant des frais en AVAX
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('influencer_fees');
    }
};
