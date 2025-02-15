<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAutoplayAndStatusToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {

            $table->unsignedBigInteger('pool_id')->nullable()->after('id');
            $table->foreign('pool_id')->references('id')->on('pools')->onDelete('cascade');

            $table->decimal('balance',18, 8)->default(10)->before('autoplay_active');
            $table->decimal('battle_balance',18, 8)->default(0.0000000)->before('wallet_address');
            $table->string('wallet_address')->unique()->nullable()->before('email');
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
            $table->dropColumn('pool_id');
            $table->dropColumn('balance');
            $table->dropColumn('battle_balance');
            $table->dropColumn('wallet_address');
            $table->dropForeign(['pool_id']);

        });
    }
}

