<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->string('name');
            $table->unsignedMediumInteger('state_id')->index('cities_test_ibfk_1');
            $table->string('state_code');
            $table->unsignedMediumInteger('country_id')->index('cities_test_ibfk_2');
            $table->char('country_code', 2);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->boolean('flag')->default(true);
            $table->string('wikiDataId')->nullable()->comment('Rapid API GeoDB Cities');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};