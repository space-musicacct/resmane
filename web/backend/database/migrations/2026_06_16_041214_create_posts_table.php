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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('kakeibo_record_id');
            $table->tinyInteger('is_ai');
            $table->integer('ai_status_id')->nullable();
            $table->integer('parent_id')->nullable();
            $table->string('content', 3000);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('kakeibo_record_id')->references('id')->on('kakeibo_records');
            $table->foreign('ai_status_id')->references('id')->on('ai_statuses');
            $table->foreign('parent_id')->references('id')->on('posts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
