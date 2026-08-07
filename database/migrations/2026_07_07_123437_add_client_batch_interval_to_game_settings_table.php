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
        \Illuminate\Support\Facades\DB::table('game_settings')->insert([
            'key' => 'client_batch_interval',
            'value' => '5000',
            'type' => 'integer',
            'description' => 'Polling interval in ms for client-driven batch engine',
            'group' => 'system',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('game_settings')
            ->where('key', 'client_batch_interval')
            ->delete();
    }
};
