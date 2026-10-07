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
        Schema::create('listing_labels', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('listing_id');
            $table->string('platform');
            $table->enum('status', ['recommended', 'not_suitable']);
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
        Schema::dropIfExists('listing_labels');
    }
};
