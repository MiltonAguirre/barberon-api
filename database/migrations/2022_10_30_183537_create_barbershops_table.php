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
        Schema::create('barbershops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('days')->nullable()->default(null);
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('user_id');  
            $table->timestamps();
        });
        Schema::table('barbershops', function($table) {
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('barbershops');
    }
};
