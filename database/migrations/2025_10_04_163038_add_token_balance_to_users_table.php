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
            // Ajoute la colonne pour stocker le solde de jetons, par défaut à 0.
            $table->unsignedBigInteger('token_balance')->default(0)->after('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Permet de supprimer la colonne si on annule la migration.
            $table->dropColumn('token_balance');
        });
    }
};