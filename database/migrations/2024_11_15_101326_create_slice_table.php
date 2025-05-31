<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSliceTable extends Migration
{
    public function up()
    {
        Schema::create('slice_table', function (Blueprint $table) {
            $table->id();
            $table->boolean('current_instance')->default(false);
            $table->unsignedInteger('instance_number');
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedBigInteger('last_user_id')->default(0);
            $table->unsignedBigInteger('ultime_user_id')->default(0);
            $table->unsignedBigInteger('bet_amount'); // Add bet amount for better filtering
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('slice_table');
    }
}
