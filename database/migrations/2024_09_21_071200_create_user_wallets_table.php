<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserWalletsTable extends Migration
{
    public function up()
    {
        Schema::create('user_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Link to users table
            $table->decimal('balance', 15, 2)->default(0); // Current wallet balance
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_wallets');
    }
}

