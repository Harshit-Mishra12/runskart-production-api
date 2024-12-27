<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatchPlayersTable extends Migration
{
    public function up()
    {
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained()->onDelete('cascade'); // Assumes matches table exists
            $table->foreignId('event_id')->constrained()->onDelete('cascade'); // Assumes matches table exists
            $table->string('external_player_id'); // Adjust type based on your needs
            $table->string('status');
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
        Schema::dropIfExists('match_players');
    }
}

