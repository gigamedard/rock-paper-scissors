<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAutoplayAndStatusToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0)->befor('autoplay_active');
            $table->string('wallet_address')->unique()->nullable()->befor('email');;
            $table->boolean('autoplay_active')->default(false)->after('email');
            $table->string('status')->default('available')->after('autoplay_active');
            $table->decimal('bet_amount')->default(0.00001)->after('status'); // Example: available, locked
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('autoplay_active');
            $table->dropColumn('status');
            $table->dropColumn('bet_amount');
        });
    }
}

