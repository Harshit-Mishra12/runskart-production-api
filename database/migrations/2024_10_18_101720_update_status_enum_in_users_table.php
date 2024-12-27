<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateStatusEnumInUsersTable extends Migration
{
    public function up()
    {
        // Use raw SQL to remove 'VERIFICATIONPENDING' from the status enum

        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('ACTIVE', 'INACTIVE')");
    }

    public function down()
    {

    }
}


