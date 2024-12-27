<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocStatusToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Adding 'doc_status' column with ENUM values 'VERIFIED', 'UNVERIFIED', 'PENDING'
            $table->enum('doc_status', ['VERIFIED', 'UNVERIFIED', 'PENDING'])->default('PENDING')->after('status');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Dropping the 'doc_status' column
            $table->dropColumn('doc_status');
        });
    }
}

