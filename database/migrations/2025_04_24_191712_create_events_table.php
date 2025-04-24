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
        Schema::create('events', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('user_id', 11);
            $table->string('name');
            $table->string('slug');
            $table->string('image');
            $table->string('address');
            $table->integer('city_id');
            $table->char('country_code', 3);
            $table->timestamp('date_start')->nullable();
            $table->timestamp('date_end')->nullable();
            $table->mediumText('description');
            $table->integer('public_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
