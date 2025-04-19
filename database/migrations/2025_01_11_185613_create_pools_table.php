<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pools', function (Blueprint $table) {
            $table->id();
            $table->string('pool_id')->default(0); // Unique identifier for the pool
            $table->string('salt')->unique()->default('abdc123'); // Salt for additional security
            $table->integer('pool_size')->default(100); // Size of the pool
            $table->decimal('base_bet', 18, 8)->default(0.00000001); // Base bet amount with higher precision
            //$table->json('users')->nullable(); // JSON column to store users (nullable) // JSON column to store users
            $table->json('premove_cids')->nullable(); // JSON column to store premove CIDs
            $table->enum('status', ['from_blockchain_running', 'from_server_waitting', 'from_server_running', 'from_server_finished'])->default('from_blockchain_running'); // Added status field
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pools');
    }
};
