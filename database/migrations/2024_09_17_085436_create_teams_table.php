<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeamsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Assumes users table exists
            $table->foreignId('event_id')->constrained()->onDelete('cascade'); // Assumes users table exists
            $table->foreignId('captain_match_player_id')->constrained('match_players')->onDelete('cascade');
            $table->string('name');
            $table->string('status'); // Define the possible values based on your application logic
            $table->integer('points_scored')->default(0); // Default value set to 0
            $table->timestamps(); // Adds created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('teams');
    }
}

