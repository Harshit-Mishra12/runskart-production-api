<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('go_live_date');
            $table->integer('team_size');
            $table->integer('batsman_limit');
            $table->integer('bowler_limit');
            $table->integer('all_rounder_limit');
            $table->decimal('team_creation_cost', 8, 2);
            $table->integer('user_participation_limit');
            $table->integer('winners_limit');
            $table->string('status')->default('CREATED');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('events');
    }
};

