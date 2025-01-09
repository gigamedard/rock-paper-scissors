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
        Schema::create('fights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('pools')->onDelete('cascade'); // Relationship with Pool
            $table->foreignId('user1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user2_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['waiting_for_both', 'waiting_for_user1', 'waiting_for_user2','waiting_for_result','completed'])->default('waiting_for_both');
            $table->enum('result', [ 'user2_win', 'user1_win','draw'])->default('draw');
            $table->decimal('base_bet_amount', 10, 2)->default(0);
            $table->decimal('max_bet_amount', 10, 2)->default(0);
            $table->enum('user1_chosed', [ 'rock', 'paper','scissors','nothing'])->default('nothing');
            $table->enum('user2_chosed', [ 'rock', 'paper','scissors','nothing'])->default('nothing');            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fights');
    }
};
