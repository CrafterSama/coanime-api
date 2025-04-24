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
        Schema::create('titles_type', function (Blueprint $table) {
            $table->integer('id', true)->unique('id');
            $table->string('name');
            $table->string('slug')->unique('slug');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['id'], 'type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titles_type');
    }
};