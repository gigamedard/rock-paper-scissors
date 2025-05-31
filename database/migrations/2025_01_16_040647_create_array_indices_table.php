<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArrayIndicesTable extends Migration
{
    public function up()
    {
        Schema::create('array_indices', function (Blueprint $table) {
            $table->id();
            $table->string('array_name')->unique(); // e.g., 'base_bet' or 'size'
            $table->integer('current_index')->default(0); // Current index for the array
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('array_indices');
    }
}
