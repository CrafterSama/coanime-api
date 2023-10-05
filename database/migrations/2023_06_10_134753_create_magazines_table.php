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
        Schema::create('magazines', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 60);
            $table->string('slug');
            $table->string('website');
            $table->string('public_time', 11)->nullable();
            $table->string('user_id', 11);
            $table->timestamp('foundation_date')->useCurrentOnUpdate()->useCurrent();
            $table->mediumText('about');
            $table->char('country_code', 3)->default('JPN');
            $table->integer('release_id');
            $table->integer('type_id');
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
        Schema::dropIfExists('magazines');
    }
};
