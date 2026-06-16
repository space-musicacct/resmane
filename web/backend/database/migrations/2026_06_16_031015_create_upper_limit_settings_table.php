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
        Schema::create('upper_limit_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('upper_limit_type_id', 50);
            $table->integer('max_value');
            $table->integer('ave_monthly_income')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // 外部キー
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('upper_limit_type_id')->references('id')->on('upper_limit_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upper_limit_settings');
    }
};
