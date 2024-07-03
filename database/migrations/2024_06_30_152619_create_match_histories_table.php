<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatchHistoriesTable extends Migration
{
    public function up()
    {
        Schema::create('match_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->string('move'); // e.g., rock, paper, or scissors
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('match_histories');
    }
}

