<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPointsToMatchPlayersTable extends Migration
{
    public function up()
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->string('points')->nullable(); // Adding role column for player's role
           
        });
    }

    public function down()
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('points'); // Dropping the role column
          
        });
    }
}
