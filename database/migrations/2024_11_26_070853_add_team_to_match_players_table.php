<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeamToMatchPlayersTable extends Migration
{
    public function up()
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->string('team')->nullable()->after('role'); // Adding team column after role
        });
    }

    public function down()
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('team'); // Dropping the team column
        });
    }
}
