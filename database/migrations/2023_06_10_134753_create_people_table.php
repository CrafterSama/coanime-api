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
        Schema::create('people', function (Blueprint $table) {
            $table->tinyInteger('id', true);
            $table->string('name');
            $table->string('first_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('japanese_name', 50);
            $table->string('slug');
            $table->date('birthday')->nullable();
            $table->enum('falldown', ['si', 'no'])->default('no');
            $table->date('falldown_date')->nullable();
            $table->integer('city_id');
            $table->char('country_code', 3);
            $table->string('areas_skills_hobbies');
            $table->string('image')->nullable();
            $table->longText('about');
            $table->string('user_id', 11);
            $table->binary('approved_info')->nullable();
            $table->string('public_time')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrent();
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
        Schema::dropIfExists('people');
    }
};
