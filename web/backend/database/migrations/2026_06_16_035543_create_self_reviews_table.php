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
        Schema::create('self_reviews', function (Blueprint $table) {
            $table->id();
            $table->integer('kakeibo_record_id');
            $table->string('review_comment', 250);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('kakeibo_record_id')->references('id')->on('kakeibo_records');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('self_reviews');
    }
};
