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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('district')->nullable();
            $table->string('price')->nullable();
            $table->string('near_to_mrt')->nullable();
            $table->string('tenure')->nullable();
            $table->string('house_type')->nullable();
            $table->string('land_size')->nullable();
            $table->string('condition')->nullable();
            $table->string('bedrooms')->nullable();
            $table->string('parking')->nullable();
            $table->string('lift')->nullable();
            $table->string('pool')->nullable();
            $table->string('granny_room')->nullable();
            $table->string('facing')->nullable();
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
        Schema::dropIfExists('clients');
    }
};
