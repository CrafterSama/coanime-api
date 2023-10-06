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
        Schema::create('titles', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name');
            $table->string('slug');
            $table->longText('sinopsis');
            $table->longText('other_titles');
            $table->string('episodies', 5)->nullable();
            $table->string('trailer_url')->nullable();
            $table->string('just_year', 5)->nullable();
            $table->date('broad_time')->nullable();
            $table->date('broad_finish')->nullable();
            $table->string('status', 100)->default('Finalizado');
            $table->integer('type_id')->default(0);
            $table->integer('rating_id')->default(7);
            $table->integer('user_id');
            $table->integer('edited_by')->nullable();
            $table->integer('public_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('titles');
    }
};
