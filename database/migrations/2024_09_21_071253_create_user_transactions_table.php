<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('user_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Link to users table
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade'); // Optional link to teams table for participation transactions
            $table->decimal('amount', 15, 2); // Transaction amount (positive for credit, negative for debit)
            $table->enum('transaction_type', ['credit', 'debit']); // Credit for wallet top-up, Debit for participation
            $table->string('description')->nullable(); // Optional description (e.g., "Wallet Top-up", "Team Participation")
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_transactions');
    }
}
