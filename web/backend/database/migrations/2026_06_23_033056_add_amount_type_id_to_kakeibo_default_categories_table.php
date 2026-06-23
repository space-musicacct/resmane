<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kakeibo_default_categories', function (Blueprint $table) {
            $table->foreignId('amount_type_id')
                ->after('id')
                ->constrained('amount_types');
        });
    }

    public function down(): void
    {
        Schema::table('kakeibo_default_categories', function (Blueprint $table) {
            $table->dropForeign(['amount_type_id']);
            $table->dropColumn('amount_type_id');
        });
    }
};
