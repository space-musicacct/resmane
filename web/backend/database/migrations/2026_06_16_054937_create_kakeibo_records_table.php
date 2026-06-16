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
            $table->foreignId('user_id')->constrained('users');
            $table->date('purchase_date');
            $table->foreignId('amount_type_id')->constrained('amount_types');
            $table->integer('amount');
            $table->string('details', 250)->nullable();
            $table->foreignId('kakeibo_default_category_id')->constrained('kakeibo_default_categories');
            $table->timestamps();
            $table->softDeletes();
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
