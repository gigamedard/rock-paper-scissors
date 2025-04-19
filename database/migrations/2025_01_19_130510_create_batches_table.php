<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // In your CreateBatchesTable migration's up() method:
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->integer('pool_size')->default(0)->index();
            $table->unsignedBigInteger('first_pool_id')->default(0);
            $table->unsignedBigInteger('last_pool_id')->default(0); // <-- ADD THIS (references pools.id)
            $table->integer('number_of_pools')->default(0);      // <-- ADD THIS
            $table->integer('max_size')->default(100);         // <-- ADD THIS (or get from config only)
            $table->integer('iteration_count')->default(0);
            $table->integer('max_iterations')->default(5);       // <-- ADD THIS (or get from config only)
            $table->enum('status', ['waiting', 'running', 'settled'])->default('waiting');
            
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
        Schema::dropIfExists('batches');
    }
}