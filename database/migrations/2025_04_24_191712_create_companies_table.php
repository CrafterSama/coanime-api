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
        Schema::create('companies', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name')->unique('estudio_name');
            $table->string('slug');
            $table->string('website');
            $table->char('country_code', 3);
            $table->timestamp('foundation_date')->default(null);
            $table->string('public_time', 11)->nullable();
            $table->string('user_id', 11);
            $table->mediumText('about');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};