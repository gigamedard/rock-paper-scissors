<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Par défaut, un utilisateur n'a pas reçu son bonus.
            $table->boolean('has_received_signup_bonus')->default(false)->after('is_eligible_to_refer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_received_signup_bonus');
        });
    }
};
