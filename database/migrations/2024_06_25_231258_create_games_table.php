<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user1_id')->constrained('users');
            $table->foreignId('user2_id')->nullable()->constrained('users');
            $table->enum('user1_choice', ['rock', 'paper', 'scissors'])->nullable();
            $table->enum('user2_choice', ['rock', 'paper', 'scissors'])->nullable();
            $table->enum('winner', ['user1', 'user2', 'draw'])->nullable();
            $table->decimal('bet_amount', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
