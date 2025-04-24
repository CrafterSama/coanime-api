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
        Schema::create('relateds', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('title_id');
            $table->integer('related_id');
            $table->set('relation', ['Precuela', 'Secuela', 'Origen', 'Adaptación', 'Spin-Off', 'Version Aternativa', 'Otro']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relateds');
    }
};