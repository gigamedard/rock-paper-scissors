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
        Schema::create('influencer_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('language');
            $table->unsignedInteger('milestone')->default(5000);
            $table->unsignedInteger('pool_milestone')->default(30000);
            $table->decimal('reward_amount', 18, 8)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('influencer_pools');
    }
};

