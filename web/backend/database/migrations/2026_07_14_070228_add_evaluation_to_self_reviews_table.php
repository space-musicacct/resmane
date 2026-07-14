<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('self_reviews', function (Blueprint $table) {
            $table->tinyInteger('evaluation')->after('review_comment');
        });

        DB::statement('ALTER TABLE self_reviews ADD CONSTRAINT chk_evaluation CHECK (evaluation >= 1 AND evaluation <= 5)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE self_reviews DROP CONSTRAINT chk_evaluation');

        Schema::table('self_reviews', function (Blueprint $table) {
            $table->dropColumn('evaluation');
        });
    }
};
