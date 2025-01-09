<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreMovesTable extends Migration
{
    public function up()
    {
        Schema::create('pre_moves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->json('moves');
            /*$table->json('hashed_moves')->nullable(); // Allow null values
            $table->string('nonce')->nullable();*/
            $table->unsignedInteger('current_index')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        
    }

    public function down()
    {
        Schema::dropIfExists('pre_moves');
    }
}
