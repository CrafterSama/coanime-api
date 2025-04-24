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
        Schema::table('cities', function (Blueprint $table) {
            $table->foreign(['state_id'], 'cities_ibfk_1')->references(['id'])->on('states')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['country_id'], 'cities_ibfk_2')->references(['id'])->on('countries')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropForeign('cities_ibfk_1');
            $table->dropForeign('cities_ibfk_2');
        });
    }
};
