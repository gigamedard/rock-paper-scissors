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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('effect_type'); // 'cooldown_reduction', 'ki_regeneration', 'ceiling_increase', 'base_bet_modifier'
            $table->decimal('effect_value', 10, 2); // Ex: 0.5 (50%), ou 1000 (1000 SNT)
            $table->string('duration_type')->default('sessions'); // 'sessions', 'time'
            $table->integer('duration_value')->default(1); // Ex: 5 sessions, ou 24 heures
            $table->decimal('price', 18, 4);
            $table->string('currency')->default('SNT'); // SNT par défaut
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
