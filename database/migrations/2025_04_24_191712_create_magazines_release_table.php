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
        Schema::create('magazines_release', function (Blueprint $table) {
            $table->integer('id', true)->index('frecuencia_id');
            $table->string('name', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id'], 'frecuencia_id_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('magazines_release');
    }
};