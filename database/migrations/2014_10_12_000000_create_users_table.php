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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('mobile_number');
            $table->string('password');
            $table->string('profile_picture')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->enum('role', ['SUPERADMIN','ADMIN', 'USER']);
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'VERIFICATIONPENDING']);
            $table->string('otp')->nullable();
            $table->string('verification_uid')->nullable();
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
};
