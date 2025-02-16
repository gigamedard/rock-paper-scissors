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
        Schema::create('f_hists', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('pool_id');
        $table->unsignedBigInteger('user1_id');
        $table->string('user1_address');
        $table->decimal('old_user1_balance', 18, 8)->default(0.0000000);
        $table->decimal('user1_balance', 18, 8);
        $table->decimal('user1_battle_balance', 18, 8);
        $table->integer('user1_premove_index');
        $table->string('user1_move');
        $table->decimal('user1_gain', 18, 8);
        $table->unsignedBigInteger('user2_id');
        $table->string('user2_address');
        $table->decimal('old_user2_balance', 18, 8)->default(0.0000000);
        $table->decimal('user2_balance', 18, 8);
        $table->decimal('user2_battle_balance', 18, 8);
        $table->integer('user2_premove_index');
        $table->string('user2_move');
        $table->decimal('user2_gain', 18, 8);
        $table->timestamps();

        // Optionally, add foreign key constraints if you want to enforce relationships:
        $table->foreign('pool_id')->references('id')->on('pools')->onDelete('cascade');
        });
    }
    

    
    public function down(): void
    {
        Schema::dropIfExists('f_hists');
    }
};

