<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoricalDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('historical_data', function (Blueprint $table) {
            $table->id();
            $table->text('encrypted_data'); // Stores the encrypted version of all fields
            $table->string('user1_wallet_address');
            $table->string('user2_wallet_address');
            $table->string('user1_move_index');
            $table->string('user2_move_index');
            $table->decimal('user1_base_bet_amount', 16, 8);
            $table->decimal('user2_base_bet_amount', 16, 8);
            $table->integer('winner_index')->nullable(); // 1 for user1, 2 for user2, null for draw
            $table->decimal('prize', 16, 8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('historical_data');
    }
}
