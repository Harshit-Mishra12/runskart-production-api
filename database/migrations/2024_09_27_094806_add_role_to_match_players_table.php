<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleToMatchPlayersTable extends Migration
{
    public function up()
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->string('role')->nullable(); // Adding role column for player's role
        });
    }

    public function down()
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('role'); // Dropping the role column
        });
    }
}
