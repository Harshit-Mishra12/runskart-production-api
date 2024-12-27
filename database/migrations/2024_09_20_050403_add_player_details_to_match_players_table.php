<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlayerDetailsToMatchPlayersTable extends Migration
{
    public function up()
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->string('name')->nullable();      // Player's name
            $table->string('country')->nullable();   // Player's country
            $table->string('image_url')->nullable(); // URL for the player's image
        });
    }

    public function down()
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('country');
            $table->dropColumn('image_url');
        });
    }
}
