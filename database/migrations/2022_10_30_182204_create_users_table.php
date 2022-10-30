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
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('api_token', 80)->unique()->nullable()->default(null);
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('data_user_id');
            $table->unsignedBigInteger('role_id');
            $table->date('last_conection');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::table('users', function($table) {
            $table->foreign('data_user_id')->references('id')->on('data_users')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles');
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
