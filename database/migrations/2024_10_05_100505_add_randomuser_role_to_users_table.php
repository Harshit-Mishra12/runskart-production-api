<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Modify the `role` column in the `users` table to remove SUPERADMIN, ADMIN, and USER
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SUPERADMIN', 'ADMIN', 'USER','RANDOMUSER')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverse the changes if necessary
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SUPERADMIN', 'ADMIN', 'USER')");
    }
};
