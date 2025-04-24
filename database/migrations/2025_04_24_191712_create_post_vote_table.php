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
        Schema::create('post_vote', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('post_id');
            $table->integer('user_id');
            $table->set('status', ['like', 'dislike', 'neutral'])->default('neutral');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_vote');
    }
};