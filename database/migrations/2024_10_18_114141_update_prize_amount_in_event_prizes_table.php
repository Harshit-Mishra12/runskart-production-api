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
        Schema::table('event_prizes', function (Blueprint $table) {
            // Update the prize_amount column to handle larger values (e.g., crores)
            $table->decimal('prize_amount', 15, 2)->change(); // Adjust the precision to allow larger amounts
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_prizes', function (Blueprint $table) {
            // Revert prize_amount to its original size
            $table->decimal('prize_amount', 8, 2)->change();
        });
    }
};
