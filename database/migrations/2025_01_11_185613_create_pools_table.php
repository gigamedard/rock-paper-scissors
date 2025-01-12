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
            $table->string('pool_id')->unique()->default(0); // Unique identifier for the pool
            $table->string('salt')->unique()->default('abdc123'); // Salt for additional security
            $table->integer('pool_size')->default(100); // Size of the pool
            $table->decimal('base_bet', 8, 2)->default(0.01); // Base bet amount
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pools');
    }
};
