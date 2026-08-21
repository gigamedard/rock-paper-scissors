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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('recovery_level')->default(1)->after('status');
            $table->integer('multiplier_level')->default(1)->after('recovery_level');
            $table->decimal('tokens', 18, 8)->default(0)->after('multiplier_level'); // Adding tokens balance as per assumption
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['recovery_level', 'multiplier_level', 'tokens']);
        });
    }
};
