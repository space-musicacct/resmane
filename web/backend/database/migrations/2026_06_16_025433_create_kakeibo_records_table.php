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
        Schema::create('kakeibo_records', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->date('purchase_date')->nullable();
            $table->integer('amount_type_id')->nullable();
            $table->integer('amount')->nullable();
            $table->string('details', 250);
            $table->integer('kakeibo_default_category_id');
            $table->timestamps();
            $table->softDeletes();

            // 外部キー
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('amount_type_id')->references('id')->on('amount_types');
            $table->foreign('kakeibo_default_category_id')->references('id')->on('kakeibo_default_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kakeibo_records');
    }
};
