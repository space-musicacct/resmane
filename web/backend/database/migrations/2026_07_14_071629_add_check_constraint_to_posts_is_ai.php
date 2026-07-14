<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE posts ADD CONSTRAINT chk_is_ai CHECK (is_ai IN (0, 1))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE posts DROP CONSTRAINT chk_is_ai');
    }
};
