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
        Schema::table('scraped_results', function (Blueprint $table) {
            $table->string('bedrooms')->nullable()->after('price');
            $table->string('land_size')->nullable()->after('bedrooms');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('scraped_results', function (Blueprint $table) {
            //
        });
    }
};
