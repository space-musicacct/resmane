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
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('kakeibo_record_id')->constrained('kakeibo_records');
            $table->foreignId('ai_status_id')->nullable()->constrained('ai_statuses');
            $table->foreignId('parent_id')->nullable()->constrained('posts');
            $table->tinyInteger('is_ai');
            $table->string('content', 3000)->nullable();
            $table->timestamps();
            $table->softDeletes();
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
